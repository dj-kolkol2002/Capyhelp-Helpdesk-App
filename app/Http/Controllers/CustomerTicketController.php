<?php

namespace App\Http\Controllers;

use App\Events\TicketMessageCreated;
use App\Mail\CustomerTicketCreatedNotification;
use App\Mail\InternalTicketMessageNotification;
use App\Mail\NewTicketNotification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ClamAvScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTicketController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Support/Create');
    }

    public function store(Request $request, ClamAvScanner $scanner): RedirectResponse
    {
        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $scanner->assertAllClean($request->file('attachments', []));

        $ticket = Ticket::create([
            'number' => $this->generateTicketNumber(),
            'requester_name' => $validated['requester_name'],
            'requester_email' => $validated['requester_email'],
            'subject' => $validated['subject'],
            'priority' => $validated['priority'] ?? 'medium',
            'channel' => 'chat',
            'status' => 'open',
        ]);

        $message = $this->createRequesterMessage($request, $ticket, $validated['body']);

        $this->notifyInternalUsersAboutNewTicket($ticket);
        $this->notifyInternalUsersAboutRequesterMessage($ticket, $message);
        Mail::to($ticket->requester_email)->queue(new CustomerTicketCreatedNotification($ticket));

        return redirect()->to($ticket->customerUrl());
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorizeCustomerAccess($request, $ticket);

        $ticket->load([
            'assigneeUser:id,name,email',
            'messages' => fn ($query) => $query->with('attachments')->oldest(),
        ]);

        return Inertia::render('Support/Show', [
            'ticket' => $ticket,
            'token' => $ticket->customer_access_token,
        ]);
    }

    public function message(Request $request, Ticket $ticket, ClamAvScanner $scanner): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (empty(trim($validated['body']))) {
            return response()->json([
                'message' => 'Wiadomość nie może być pusta.',
                'errors' => ['body' => ['Wiadomość nie może być pusta.']],
            ], 422);
        }

        $scanner->assertAllClean($request->file('attachments', []));

        $message = $this->createRequesterMessage($request, $ticket, $validated['body']);
        $ticket->touch();

        broadcast(new TicketMessageCreated($message))->toOthers();
        $this->notifyInternalUsersAboutRequesterMessage($ticket, $message);

        return response()->json([
            'message' => $message,
        ], 201);
    }

    private function authorizeCustomerAccess(Request $request, Ticket $ticket): void
    {
        abort_if(
            ! $ticket->customer_access_token || ! hash_equals($ticket->customer_access_token, (string) $request->query('token')),
            403,
            'Link do zgłoszenia jest nieprawidłowy.'
        );
    }

    private function createRequesterMessage(Request $request, Ticket $ticket, string $body): TicketMessage
    {
        $message = $ticket->messages()->create([
            'user_id' => null,
            'author_name' => $ticket->requester_name,
            'author_email' => $ticket->requester_email,
            'author_type' => 'requester',
            'body' => trim($body),
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

        return $message->load('attachments');
    }

    private function notifyInternalUsersAboutNewTicket(Ticket $ticket): void
    {
        User::query()
            ->whereIn('role', ['admin', 'agent'])
            ->get()
            ->filter(fn (User $user): bool => $user->wantsNotification('newTicket'))
            ->each(function (User $user) use ($ticket): void {
                UserNotification::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'new_ticket',
                    'title' => 'Nowe zgłoszenie '.$ticket->number,
                    'body' => $ticket->subject,
                ]);

                Mail::to($user->email)->queue(new NewTicketNotification($ticket));
            });
    }

    private function notifyInternalUsersAboutRequesterMessage(Ticket $ticket, TicketMessage $message): void
    {
        $recipients = $ticket->assignee
            ? User::query()->whereKey($ticket->assignee)->get()
            : User::query()->whereIn('role', ['admin', 'agent'])->get();

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

    private function generateTicketNumber(): string
    {
        $lastTicket = Ticket::latest('id')->first();
        $nextNumber = ($lastTicket->id ?? 0) + 1;

        return 'TKT-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
