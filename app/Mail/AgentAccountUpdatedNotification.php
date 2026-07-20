<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentAccountUpdatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $agent, public array $changes, public bool $passwordChanged) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zmieniono dane Twojego konta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.agent-account-updated-notification',
        );
    }
}
