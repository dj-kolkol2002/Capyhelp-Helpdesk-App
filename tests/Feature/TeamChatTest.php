<?php

namespace Tests\Feature;

use App\Events\TeamChatMessageCreated;
use App\Mail\TeamChatMessageNotification;
use App\Models\TeamChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_send_team_chat_message(): void
    {
        Event::fake([TeamChatMessageCreated::class]);
        Mail::fake();
        $agent = User::factory()->agent()->create();
        $recipient = User::factory()->admin()->create();

        $response = $this->actingAs($agent)->postJson('/team-chat/messages', [
            'recipient_id' => $recipient->id,
            'body' => 'Cześć zespół **ważne** 🙂',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message.body', 'Cześć zespół **ważne** 🙂');

        $this->assertDatabaseHas('team_chat_messages', [
            'user_id' => $agent->id,
            'recipient_id' => $recipient->id,
            'body' => 'Cześć zespół **ważne** 🙂',
        ]);
        Event::assertDispatched(TeamChatMessageCreated::class);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'type' => 'team_chat_message',
            'title' => 'Nowa wiadomość od '.$agent->name,
        ]);
        Mail::assertQueued(TeamChatMessageNotification::class, function (TeamChatMessageNotification $mail) use ($recipient) {
            return $mail->hasTo($recipient->email)
                && $mail->teamChatMessage->body === 'Cześć zespół **ważne** 🙂';
        });
    }

    public function test_team_chat_message_can_have_attachments(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->agent()->create();

        $response = $this->actingAs($admin)->post('/team-chat/messages', [
            'recipient_id' => $recipient->id,
            'body' => 'Plik do sprawdzenia',
            'attachments' => [
                UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();

        $message = TeamChatMessage::query()->with('attachments')->firstOrFail();
        $this->assertCount(1, $message->attachments);
        $this->assertSame('brief.pdf', $message->attachments->first()->original_name);
        Storage::disk('local')->assertExists($message->attachments->first()->path);

        $this->actingAs($recipient)
            ->get($message->attachments->first()->url)
            ->assertOk();
    }

    public function test_team_chat_message_body_is_required(): void
    {
        $agent = User::factory()->agent()->create();
        $recipient = User::factory()->admin()->create();

        $response = $this->actingAs($agent)->postJson('/team-chat/messages', [
            'recipient_id' => $recipient->id,
            'body' => '   ',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('team_chat_messages', 0);
    }

    public function test_guest_cannot_send_team_chat_message(): void
    {
        $response = $this->postJson('/team-chat/messages', [
            'recipient_id' => 1,
            'body' => 'Nie powinno wejść',
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_fetch_private_conversation_with_selected_employee(): void
    {
        $agent = User::factory()->agent()->create();
        $recipient = User::factory()->admin()->create();
        $other = User::factory()->agent()->create();

        TeamChatMessage::create([
            'user_id' => $agent->id,
            'recipient_id' => $recipient->id,
            'body' => 'Widoczna wiadomość',
        ]);
        TeamChatMessage::create([
            'user_id' => $other->id,
            'recipient_id' => $recipient->id,
            'body' => 'Nie powinna być widoczna',
        ]);

        $response = $this->actingAs($agent)->getJson('/team-chat/messages?recipient_id='.$recipient->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Widoczna wiadomość');
    }

    public function test_user_cannot_send_team_chat_message_to_self(): void
    {
        $agent = User::factory()->agent()->create();

        $response = $this->actingAs($agent)->postJson('/team-chat/messages', [
            'recipient_id' => $agent->id,
            'body' => 'Sam do siebie',
        ]);

        $response->assertUnprocessable();
    }
}
