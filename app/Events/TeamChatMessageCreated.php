<?php

namespace App\Events;

use App\Models\TeamChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TeamChatMessage $message)
    {
        $this->message->loadMissing('user:id,name,email,role,avatar_path', 'recipient:id,name,email,role,avatar_path', 'attachments');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('team-chat.users.'.$this->message->recipient_id);
    }

    public function broadcastAs(): string
    {
        return 'team-chat.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'user_id' => $this->message->user_id,
                'recipient_id' => $this->message->recipient_id,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toJSON(),
                'user' => $this->message->user,
                'recipient' => $this->message->recipient,
                'attachments' => $this->message->attachments,
            ],
        ];
    }
}
