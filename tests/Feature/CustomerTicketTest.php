<?php

namespace Tests\Feature;

use App\Events\TicketMessageCreated;
use App\Mail\CustomerTicketCreatedNotification;
use App\Mail\InternalTicketMessageNotification;
use App\Mail\NewTicketNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_ticket_and_get_private_link(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $response = $this->post('/support/tickets', [
            'requester_name' => 'Jan Klient',
            'requester_email' => 'jan@example.com',
            'subject' => 'Nie działa logowanie',
            'priority' => 'high',
            'body' => 'Nie mogę zalogować się do panelu.',
        ]);

        $ticket = Ticket::query()->firstOrFail();

        $response->assertRedirect($ticket->customerUrl());
        $this->assertDatabaseHas('tickets', [
            'requester_email' => 'jan@example.com',
            'subject' => 'Nie działa logowanie',
            'channel' => 'chat',
        ]);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'author_type' => 'requester',
            'body' => 'Nie mogę zalogować się do panelu.',
        ]);
        Mail::assertQueued(CustomerTicketCreatedNotification::class, fn (CustomerTicketCreatedNotification $mail) => $mail->hasTo('jan@example.com'));
        Mail::assertQueued(NewTicketNotification::class);
        Mail::assertQueued(InternalTicketMessageNotification::class);
    }

    public function test_customer_ticket_requires_valid_token(): void
    {
        $ticket = Ticket::factory()->create();

        $this->get("/support/tickets/{$ticket->id}?token=wrong-token")->assertForbidden();
        $this->get($ticket->customerUrl())->assertOk();
    }

    public function test_customer_can_reply_with_attachment(): void
    {
        Storage::fake('local');
        Event::fake([TicketMessageCreated::class]);
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create([
            'assignee' => null,
        ]);

        $response = $this->post(
            "/support/tickets/{$ticket->id}/messages?token={$ticket->customer_access_token}",
            [
                'body' => 'Dosyłam zrzut ekranu.',
                'attachments' => [
                    UploadedFile::fake()->create('screen.pdf', 120, 'application/pdf'),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertCreated()
            ->assertJsonPath('message.author_type', 'requester')
            ->assertJsonPath('message.attachments.0.original_name', 'screen.pdf');

        $message = $ticket->messages()->with('attachments')->latest()->firstOrFail();
        $attachment = $message->attachments->first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get($attachment->url.'?token='.$ticket->customer_access_token)
            ->assertOk();
        Event::assertDispatched(TicketMessageCreated::class);
        Mail::assertQueued(InternalTicketMessageNotification::class, fn (InternalTicketMessageNotification $mail) => $mail->hasTo($admin->email));
    }
}
