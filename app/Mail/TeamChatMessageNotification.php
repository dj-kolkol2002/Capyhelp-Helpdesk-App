<?php

namespace App\Mail;

use App\Models\TeamChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamChatMessageNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TeamChatMessage $teamChatMessage)
    {
        $this->teamChatMessage->loadMissing('user', 'recipient', 'attachments');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowa wiadomosc od '.$this->teamChatMessage->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.team-chat-message-notification',
        );
    }
}
