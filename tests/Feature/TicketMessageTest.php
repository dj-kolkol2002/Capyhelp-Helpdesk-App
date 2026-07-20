<?php

namespace Tests\Feature;

use App\Events\TicketMessageCreated;
use App\Mail\InternalTicketMessageNotification;
use App\Mail\TicketMessageNotification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\ClamAvScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketMessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->agent()->create();
        $this->ticket = Ticket::factory()->create([
            'assignee' => $this->user->id,
        ]);
    }

    public function test_message_can_be_sent_by_authenticated_user(): void
    {
        Event::fake([TicketMessageCreated::class]);
        Mail::fake();

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => 'Test message']
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('ticket_messages', [
            'body' => 'Test message',
            'author_type' => 'agent',
        ]);
        Event::assertDispatched(TicketMessageCreated::class);
        Mail::assertQueued(TicketMessageNotification::class, function (TicketMessageNotification $mail) {
            return $mail->hasTo($this->ticket->requester_email)
                && $mail->ticketMessage->body === 'Test message';
        });
    }

    public function test_requester_message_notifies_assignee_in_app_and_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Requester follow-up',
                'author_type' => 'requester',
            ]
        );

        $response->assertStatus(201);
        Mail::assertQueued(InternalTicketMessageNotification::class, function (InternalTicketMessageNotification $mail) {
            return $mail->hasTo($this->user->email)
                && $mail->ticketMessage->body === 'Requester follow-up';
        });
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->user->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'requester_message',
            'title' => 'Nowa wiadomość w '.$this->ticket->number,
        ]);
    }

    public function test_message_with_only_whitespace_is_rejected(): void
    {
        $countBefore = TicketMessage::count();

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => '     ']
        );

        $countAfter = TicketMessage::count();

        $this->assertEquals($countBefore, $countAfter, 'No message should be created');
    }

    public function test_empty_message_is_rejected(): void
    {
        $countBefore = TicketMessage::count();

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => '']
        );

        $countAfter = TicketMessage::count();

        $this->assertEquals($countBefore, $countAfter, 'No message should be created');
    }

    public function test_message_body_is_trimmed(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => '  Test message  ']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('ticket_messages', [
            'body' => 'Test message',
        ]);
    }

    public function test_author_type_can_be_specified(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Test message',
                'author_type' => 'requester',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('ticket_messages', [
            'author_type' => 'requester',
        ]);
    }

    public function test_message_can_have_attachments(): void
    {
        Storage::fake('local');
        Mail::fake();

        $response = $this->actingAs($this->user)->post(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Załączam plik do ticketa',
                'attachments' => [
                    UploadedFile::fake()->create('screen.pdf', 120, 'application/pdf'),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonPath('message.attachments.0.original_name', 'screen.pdf');

        $message = TicketMessage::query()->with('attachments')->firstOrFail();
        $this->assertCount(1, $message->attachments);
        Storage::disk('local')->assertExists($message->attachments->first()->path);
        $this->assertSame('local', $message->attachments->first()->disk);
    }

    public function test_assigned_user_can_download_ticket_attachment(): void
    {
        Storage::fake('local');
        Mail::fake();

        $this->actingAs($this->user)->post(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Załączam plik do pobrania',
                'attachments' => [
                    UploadedFile::fake()->create('screen.pdf', 120, 'application/pdf'),
                ],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(201);

        $attachment = TicketMessage::query()->with('attachments')->firstOrFail()->attachments->first();

        $this->actingAs($this->user)
            ->get($attachment->url)
            ->assertOk();
    }

    public function test_unassigned_user_cannot_download_ticket_attachment(): void
    {
        Storage::fake('local');
        Mail::fake();

        $this->actingAs($this->user)->post(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Załączam plik prywatny',
                'attachments' => [
                    UploadedFile::fake()->create('screen.pdf', 120, 'application/pdf'),
                ],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(201);

        $attachment = TicketMessage::query()->with('attachments')->firstOrFail()->attachments->first();
        $otherAgent = User::factory()->agent()->create();

        $this->actingAs($otherAgent)
            ->get($attachment->url)
            ->assertForbidden();
    }

    public function test_infected_attachment_is_rejected_before_message_is_created(): void
    {
        Storage::fake('local');
        Mail::fake();

        $this->mock(ClamAvScanner::class, function ($mock): void {
            $mock->shouldReceive('assertAllClean')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'attachments' => ['Plik screen.pdf został odrzucony przez skaner antywirusowy.'],
                ]));
        });

        $response = $this->actingAs($this->user)->post(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Załączam podejrzany plik',
                'attachments' => [
                    UploadedFile::fake()->create('screen.pdf', 120, 'application/pdf'),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('attachments');

        $this->assertDatabaseMissing('ticket_messages', [
            'body' => 'Załączam podejrzany plik',
        ]);
    }

    public function test_invalid_author_type_is_rejected(): void
    {
        $countBefore = TicketMessage::count();

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            [
                'body' => 'Test message',
                'author_type' => 'invalid_type',
            ]
        );

        $countAfter = TicketMessage::count();

        $this->assertEquals($countBefore, $countAfter, 'No message should be created with invalid author_type');
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $response = $this->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => 'Test message']
        );

        $response->assertUnauthorized();
    }

    public function test_agent_cannot_send_message_to_unassigned_ticket(): void
    {
        $otherAgent = User::factory()->agent()->create();
        $otherTicket = Ticket::factory()->create([
            'assignee' => $otherAgent->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/tickets/{$otherTicket->id}/messages",
            ['body' => 'I should not be able to post here']
        );

        $response->assertStatus(403);
        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $otherTicket->id,
            'body' => 'I should not be able to post here',
        ]);
    }

    public function test_admin_can_send_message_to_any_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $otherTicket = Ticket::factory()->create([
            'assignee' => null,
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/tickets/{$otherTicket->id}/messages",
            ['body' => 'Admin note']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $otherTicket->id,
            'body' => 'Admin note',
        ]);
    }

    public function test_ticket_updated_at_is_touched(): void
    {
        $originalUpdatedAt = $this->ticket->updated_at;

        sleep(1);

        $this->actingAs($this->user)->postJson(
            "/tickets/{$this->ticket->id}/messages",
            ['body' => 'Test message']
        );

        $this->ticket->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $this->ticket->updated_at);
    }
}
