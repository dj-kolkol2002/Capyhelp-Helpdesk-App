<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClamAvScanner
{
    public function assertAllClean(iterable $files, string $field = 'attachments'): void
    {
        if (! config('services.clamav.enabled')) {
            return;
        }

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $this->assertClean($file, $field);
            }
        }
    }

    public function assertClean(UploadedFile $file, string $field = 'attachments'): void
    {
        if (! config('services.clamav.enabled')) {
            return;
        }

        $response = $this->scan($file, $field);

        if (str_contains($response, 'FOUND')) {
            throw ValidationException::withMessages([
                $field => ['Plik '.$file->getClientOriginalName().' został odrzucony przez skaner antywirusowy.'],
            ]);
        }

        if (! str_contains($response, 'OK')) {
            throw ValidationException::withMessages([
                $field => ['Nie udało się potwierdzić bezpieczeństwa pliku '.$file->getClientOriginalName().'. Spróbuj ponownie za chwilę.'],
            ]);
        }
    }

    private function scan(UploadedFile $file, string $field): string
    {
        $path = $file->getRealPath();

        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                $field => ['Nie udało się odczytać przesłanego pliku do skanowania.'],
            ]);
        }

        $host = (string) config('services.clamav.host', 'clamav');
        $port = (int) config('services.clamav.port', 3310);
        $timeout = (int) config('services.clamav.timeout', 30);

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);

        if (! $socket) {
            throw ValidationException::withMessages([
                $field => ['Skaner antywirusowy jest chwilowo niedostępny. Spróbuj ponownie za chwilę.'],
            ]);
        }

        stream_set_timeout($socket, $timeout);
        $handle = null;

        try {
            $this->write($socket, "zINSTREAM\0", $field);

            $handle = fopen($path, 'rb');

            if (! $handle) {
                throw new RuntimeException('Cannot open uploaded file for antivirus scan.');
            }

            while (! feof($handle)) {
                $chunk = fread($handle, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('Cannot read uploaded file for antivirus scan.');
                }

                if ($chunk === '') {
                    continue;
                }

                $this->write($socket, pack('N', strlen($chunk)).$chunk, $field);
            }

            $this->write($socket, pack('N', 0), $field);

            $response = trim((string) stream_get_contents($socket));
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                $field => ['Nie udało się przeskanować przesłanego pliku. Spróbuj ponownie za chwilę.'],
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            fclose($socket);
        }

        return $response;
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $payload, string $field): void
    {
        $length = strlen($payload);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($socket, substr($payload, $offset));

            if ($written === false || $written === 0) {
                throw ValidationException::withMessages([
                    $field => ['Nie udało się przesłać pliku do skanera antywirusowego.'],
                ]);
            }

            $offset += $written;
        }
    }
}
