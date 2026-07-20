<?php

namespace App\Http\Controllers;

use App\Events\TeamChatMessageCreated;
use App\Mail\TeamChatMessageNotification;
use App\Models\TeamChatMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ClamAvScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TeamChatMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $recipient = $this->resolveRecipient($request, (int) $validated['recipient_id']);

        $messages = TeamChatMessage::query()
            ->with('user:id,name,email,role,avatar_path', 'recipient:id,name,email,role,avatar_path', 'attachments')
            ->where(function ($query) use ($request, $recipient) {
                $query
                    ->where('user_id', $request->user()->id)
                    ->where('recipient_id', $recipient->id);
            })
            ->orWhere(function ($query) use ($request, $recipient) {
                $query
                    ->where('user_id', $recipient->id)
                    ->where('recipient_id', $request->user()->id);
            })
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, ClamAvScanner $scanner): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $recipient = $this->resolveRecipient($request, (int) $validated['recipient_id']);

        if (empty(trim($validated['body']))) {
            return response()->json([
                'message' => 'Wiadomość nie może być pusta.',
                'errors' => ['body' => ['Wiadomość nie może być pusta.']],
            ], 422);
        }

        $scanner->assertAllClean($request->file('attachments', []));

        $message = TeamChatMessage::create([
            'user_id' => $request->user()->id,
            'recipient_id' => $recipient->id,
            'body' => trim($validated['body']),
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $message->attachments()->create([
                'disk' => 'local',
                'path' => $file->store('team-chat', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $message->load('user:id,name,email,role,avatar_path', 'recipient:id,name,email,role,avatar_path', 'attachments');

        broadcast(new TeamChatMessageCreated($message))->toOthers();
        $this->notifyRecipient($recipient, $message);

        return response()->json([
            'message' => $message,
        ], 201);
    }

    private function resolveRecipient(Request $request, int $recipientId): User
    {
        abort_if($recipientId === $request->user()->id, 422, 'Nie możesz wysłać wiadomości do siebie.');

        $recipient = User::query()
            ->whereKey($recipientId)
            ->whereIn('role', ['admin', 'agent'])
            ->first();

        abort_unless($recipient, 422, 'Wybierz poprawnego pracownika.');

        return $recipient;
    }

    private function notifyRecipient(User $recipient, TeamChatMessage $message): void
    {
        if (! $recipient->wantsNotification('teamChat')) {
            return;
        }

        UserNotification::create([
            'user_id' => $recipient->id,
            'ticket_id' => null,
            'type' => 'team_chat_message',
            'title' => 'Nowa wiadomość od '.$message->user->name,
            'body' => str($message->body)->limit(140)->toString(),
        ]);

        Mail::to($recipient->email)->queue(new TeamChatMessageNotification($message));
    }
}
