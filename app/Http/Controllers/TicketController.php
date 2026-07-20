<?php

namespace App\Http\Controllers;

use App\Mail\NewTicketNotification;
use App\Mail\TicketAssignedNotification;
use App\Mail\TicketUpdatedNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of tickets (admin only).
     */
    public function index(): Response
    {
        $this->authorize('create', Ticket::class);

        $tickets = Ticket::query()
            ->with([
                'assigneeUser:id,name,email',
                'messages' => fn ($query) => $query->oldest(),
            ])
            ->latest('updated_at')
            ->get();

        return Inertia::render('Dashboard', [
            'tickets' => $tickets,
            'agents' => User::query()
                ->where('role', 'agent')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Show the form for creating a new ticket (admin only).
     */
    public function create(): Response
    {
        $this->authorize('create', Ticket::class);

        $agents = User::query()
            ->where('role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Tickets/Form', [
            'agents' => $agents,
            'mode' => 'create',
        ]);
    }

    /**
     * Store a newly created ticket in storage (admin only).
     */
    public function store(): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = request()->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            'channel' => 'required|in:email,phone,chat,in-person',
            'assignee' => 'nullable|exists:users,id',
        ]);

        $ticket = Ticket::create([
            ...$validated,
            'number' => $this->generateTicketNumber(),
            'status' => 'open',
        ]);

        $this->sendNewTicketNotifications($ticket);
        $this->sendAssignedTicketNotification($ticket);
        $this->createNewTicketAppNotifications($ticket);
        $this->createAssignedTicketAppNotification($ticket);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'assigneeUser:id,name,email',
            'messages' => fn ($query) => $query->with('attachments')->oldest(),
        ]);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'agents' => User::query()
                ->where('role', 'agent')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Show the form for editing the specified ticket.
     */
    public function edit(Ticket $ticket): Response
    {
        $this->authorize('update', $ticket);

        $agents = User::query()
            ->where('role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Tickets/Form', [
            'ticket' => $ticket,
            'agents' => $agents,
            'mode' => 'edit',
        ]);
    }

    /**
     * Update the specified ticket in storage.
     */
    public function update(Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = request()->validate([
            'subject' => 'nullable|string|max:255',
            'status' => 'nullable|in:open,in_progress,resolved,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'channel' => 'nullable|in:email,phone,chat,in-person',
            'assignee' => 'nullable|exists:users,id',
        ]);

        $previousValues = $ticket->only(['subject', 'status', 'priority', 'channel', 'assignee']);
        $previousAssignee = $ticket->assignee;

        $ticket->update($validated);
        $freshTicket = $ticket->fresh();
        $trackedChanges = $this->trackedTicketChanges($previousValues, $freshTicket, $validated);

        if (array_key_exists('assignee', $validated) && $ticket->assignee !== $previousAssignee) {
            $this->sendAssignedTicketNotification($freshTicket);
            $this->createAssignedTicketAppNotification($freshTicket);
        }

        if ($trackedChanges !== []) {
            $this->sendTicketUpdatedNotifications($freshTicket, $trackedChanges);
            $this->createTicketUpdatedAppNotifications($freshTicket, $trackedChanges);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated successfully.');
    }

    /**
     * Delete the specified ticket from storage (admin only).
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    /**
     * Generate a unique ticket number.
     */
    private function generateTicketNumber(): string
    {
        $lastTicket = Ticket::latest('id')->first();
        $nextNumber = ($lastTicket->id ?? 0) + 1;

        return 'TKT-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function sendNewTicketNotifications(Ticket $ticket): void
    {
        User::query()
            ->whereIn('role', ['admin', 'agent'])
            ->get()
            ->filter(fn (User $user): bool => $user->wantsNotification('newTicket'))
            ->each(fn (User $user) => Mail::to($user->email)->queue(new NewTicketNotification($ticket)));
    }

    private function sendAssignedTicketNotification(?Ticket $ticket): void
    {
        if (! $ticket?->assignee) {
            return;
        }

        $assignee = User::find($ticket->assignee);

        if (! $assignee?->wantsNotification('assignedTicket')) {
            return;
        }

        Mail::to($assignee->email)->queue(new TicketAssignedNotification($ticket));
    }

    private function createNewTicketAppNotifications(Ticket $ticket): void
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
                    'body' => $ticket->requester_name.' utworzył(a) zgłoszenie: '.$ticket->subject,
                ]);
            });
    }

    private function createAssignedTicketAppNotification(?Ticket $ticket): void
    {
        if (! $ticket?->assignee) {
            return;
        }

        $assignee = User::find($ticket->assignee);

        if (! $assignee?->wantsNotification('assignedTicket')) {
            return;
        }

        UserNotification::create([
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'type' => 'ticket_assigned',
            'title' => 'Przypisano zgłoszenie '.$ticket->number,
            'body' => 'Zgłoszenie trafiło do Twojej kolejki: '.$ticket->subject,
        ]);
    }

    private function trackedTicketChanges(array $previousValues, Ticket $ticket, array $validated): array
    {
        $labels = [
            'subject' => 'Temat',
            'status' => 'Status',
            'priority' => 'Priorytet',
            'channel' => 'Kanal',
        ];

        $changes = [];

        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $from = $previousValues[$field] ?? null;
            $to = $ticket->{$field};

            if ((string) $from === (string) $to) {
                continue;
            }

            $changes[$label] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        return $changes;
    }

    private function ticketUpdateRecipients(Ticket $ticket): Collection
    {
        if ($ticket->assignee) {
            return User::query()->whereKey($ticket->assignee)->get();
        }

        return User::query()->where('role', 'admin')->get();
    }

    private function sendTicketUpdatedNotifications(Ticket $ticket, array $changes): void
    {
        Mail::to($ticket->requester_email)->queue(new TicketUpdatedNotification($ticket, $changes));

        $this->ticketUpdateRecipients($ticket)
            ->filter(fn (User $user): bool => $user->wantsNotification('ticketUpdated'))
            ->each(fn (User $user) => Mail::to($user->email)->queue(new TicketUpdatedNotification($ticket, $changes)));
    }

    private function createTicketUpdatedAppNotifications(Ticket $ticket, array $changes): void
    {
        $body = collect($changes)
            ->map(fn (array $change, string $field): string => $field.': '.($change['from'] ?? '-').' -> '.($change['to'] ?? '-'))
            ->join(', ');

        $this->ticketUpdateRecipients($ticket)
            ->filter(fn (User $user): bool => $user->wantsNotification('ticketUpdated'))
            ->each(function (User $user) use ($ticket, $body): void {
                UserNotification::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_updated',
                    'title' => 'Zmieniono zgłoszenie '.$ticket->number,
                    'body' => $body,
                ]);
            });
    }
}
