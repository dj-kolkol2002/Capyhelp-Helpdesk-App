<?php

namespace App\Http\Controllers;

use App\Events\TicketMessageCreated;
use App\Mail\InternalTicketMessageNotification;
use App\Mail\TicketMessageNotification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ClamAvScanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TicketMessageController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Ticket $ticket, ClamAvScanner $scanner): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'author_type' => ['sometimes', 'in:agent,requester'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (empty(trim($validated['body']))) {
            return response()->json([
                'message' => 'The body field must contain at least one non-whitespace character.',
                'errors' => ['body' => ['The body field must contain at least one non-whitespace character.']],
            ], 422);
        }

        $scanner->assertAllClean($request->file('attachments', []));

        $user = $request->user();
        $authorType = $validated['author_type'] ?? 'agent';

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'author_name' => $user->name,
            'author_email' => $user->email,
            'author_type' => $authorType,
            'body' => trim($validated['body']),
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $message->attachments()->create([
                'disk' => 'local',
                'path' => $file->store('ticket-messages', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $message->load('attachments');

        $ticket->touch();

        broadcast(new TicketMessageCreated($message))->toOthers();

        if ($message->author_type === 'agent') {
            Mail::to($ticket->requester_email)->queue(new TicketMessageNotification($message));
        } else {
            $this->notifyInternalUsersAboutRequesterMessage($ticket, $message);
        }

        return response()->json([
            'message' => $message,
        ], 201);
    }

    private function notifyInternalUsersAboutRequesterMessage(Ticket $ticket, TicketMessage $message): void
    {
        $recipients = $ticket->assignee
            ? User::query()->whereKey($ticket->assignee)->get()
            : User::query()->where('role', 'admin')->get();

        $recipients
            ->filter(fn (User $user): bool => $user->wantsNotification('ticketMessage'))
            ->each(function (User $user) use ($ticket, $message): void {
                UserNotification::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'requester_message',
                    'title' => 'Nowa wiadomość w '.$ticket->number,
                    'body' => str($message->body)->limit(140)->toString(),
                ]);

                Mail::to($user->email)->queue(new InternalTicketMessageNotification($message));
            });
    }
}
