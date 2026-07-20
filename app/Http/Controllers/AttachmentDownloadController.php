<?php

namespace App\Http\Controllers;

use App\Models\TeamChatAttachment;
use App\Models\TicketMessageAttachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    use AuthorizesRequests;

    public function ticket(Request $request, TicketMessageAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message.ticket');
        $ticket = $attachment->message->ticket;

        if ($request->user()) {
            $this->authorize('view', $ticket);
        } else {
            abort_if(
                ! $ticket->customer_access_token || ! hash_equals($ticket->customer_access_token, (string) $request->query('token')),
                403,
                'Nie masz dostępu do tego załącznika.'
            );
        }

        return $this->download($attachment->disk, $attachment->path, $attachment->original_name);
    }

    public function teamChat(Request $request, TeamChatAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message');
        $message = $attachment->message;
        $userId = (int) $request->user()->id;

        abort_unless(
            (int) $message->user_id === $userId || (int) $message->recipient_id === $userId,
            403,
            'Nie masz dostępu do tego załącznika.'
        );

        return $this->download($attachment->disk, $attachment->path, $attachment->original_name);
    }

    private function download(string $disk, string $path, string $name): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'Załącznik nie istnieje.');

        return Storage::disk($disk)->download($path, $name);
    }
}
