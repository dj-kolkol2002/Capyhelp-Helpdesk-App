<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentAccountCreatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $agent) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Utworzono Twoje konto w CAPYHELP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.agent-account-created-notification',
        );
    }
}
