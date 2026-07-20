<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public UserNotification $notification)
    {
        $this->notification->loadMissing([
            'ticket.assigneeUser:id,name,email',
            'ticket.messages' => fn ($query) => $query->oldest(),
        ]);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'app.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'ticket_id' => $this->notification->ticket_id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'read_at' => $this->notification->read_at?->toJSON(),
                'created_at' => $this->notification->created_at?->toJSON(),
                'updated_at' => $this->notification->updated_at?->toJSON(),
                'ticket' => $this->notification->ticket,
            ],
        ];
    }
}
