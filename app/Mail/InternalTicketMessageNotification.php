<?php

namespace App\Mail;

use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InternalTicketMessageNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TicketMessage $ticketMessage)
    {
        $this->ticketMessage->loadMissing('ticket');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowa wiadomosc klienta w zgloszeniu '.$this->ticketMessage->ticket->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.internal-ticket-message-notification',
        );
    }
}
