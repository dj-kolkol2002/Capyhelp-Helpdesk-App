<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketUpdatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public array $changes)
    {
        $this->ticket->loadMissing('assigneeUser');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zmieniono zgloszenie '.$this->ticket->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket-updated-notification',
        );
    }
}
