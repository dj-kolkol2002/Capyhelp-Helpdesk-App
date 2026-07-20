<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlaWarningNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public string $dueAtLabel)
    {
        $this->ticket->loadMissing('assigneeUser');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SLA: zgloszenie '.$this->ticket->number.' wymaga reakcji',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sla-warning-notification',
        );
    }
}
