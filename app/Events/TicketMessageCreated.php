<?php

namespace App\Events;

use App\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketMessage $message)
    {
        $this->message->loadMissing('ticket', 'attachments');
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('tickets.'.$this->message->ticket_id),
        ];

        if ($this->message->ticket?->customer_access_token) {
            $channels[] = new Channel('customer-tickets.'.$this->message->ticket_id.'.'.$this->message->ticket->customer_access_token);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'user_id' => $this->message->user_id,
                'author_name' => $this->message->author_name,
                'author_email' => $this->message->author_email,
                'author_type' => $this->message->author_type,
                'body' => $this->message->body,
                'attachments' => $this->message->attachments,
                'created_at' => $this->message->created_at?->toJSON(),
                'updated_at' => $this->message->updated_at?->toJSON(),
            ],
        ];
    }
}
