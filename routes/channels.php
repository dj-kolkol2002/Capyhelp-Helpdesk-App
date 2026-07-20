<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tickets.{ticketId}', function ($user, int $ticketId) {
    $ticket = Ticket::find($ticketId);

    return $ticket && (
        $user->isAdmin()
        || ($user->isAgent() && (int) $ticket->assignee === (int) $user->id)
    );
});

Broadcast::channel('team-chat.users.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId && ($user->isAdmin() || $user->isAgent());
});

Broadcast::channel('users.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
