<?php

namespace Tests\Feature;

use App\Mail\AgentAccountCreatedNotification;
use App\Mail\AgentAccountUpdatedNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create();
    }

    /**
     * Admin can view users list
     */
    public function test_admin_can_view_users_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/users');

        $response->assertStatus(200);
    }

    /**
     * Agent cannot view users list
     */
    public function test_agent_cannot_view_users_list(): void
    {
        $response = $this->actingAs($this->agent)->get('/users');

        $response->assertStatus(403);
    }

    /**
     * Admin can create user
     */
    public function test_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/users/create');

        $response->assertStatus(200);
    }

    /**
     * Agent cannot create user
     */
    public function test_agent_cannot_view_create_form(): void
    {
        $response = $this->actingAs($this->agent)->get('/users/create');

        $response->assertStatus(403);
    }

    /**
     * Admin can store user
     */
    public function test_admin_can_store_user(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->post('/users', [
            'name' => 'New Agent',
            'email' => 'newagent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'agent',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'email' => 'newagent@example.com',
            'role' => 'agent',
        ]);
        $createdUser = User::where('email', 'newagent@example.com')->firstOrFail();
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $createdUser->id,
            'type' => 'account_created',
            'title' => 'Utworzono Twoje konto',
        ]);
        Mail::assertQueued(AgentAccountCreatedNotification::class, function (AgentAccountCreatedNotification $mail) {
            return $mail->hasTo('newagent@example.com')
                && $mail->agent->email === 'newagent@example.com';
        });
    }

    public function test_admin_json_store_notifies_new_agent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'API New Agent',
            'email' => 'apinewagent@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ]);

        $response->assertCreated();
        $createdUser = User::where('email', 'apinewagent@example.com')->firstOrFail();
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $createdUser->id,
            'type' => 'account_created',
        ]);
        Mail::assertQueued(AgentAccountCreatedNotification::class, fn (AgentAccountCreatedNotification $mail) => $mail->hasTo('apinewagent@example.com'));
    }

    public function test_agent_can_view_team_members_json_list(): void
    {
        $response = $this->actingAs($this->agent)->getJson('/api/users/list');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'email' => $this->admin->email,
                'role' => 'admin',
            ])
            ->assertJsonFragment([
                'email' => $this->agent->email,
                'role' => 'agent',
            ]);
    }

    public function test_agent_cannot_create_user_from_json_api(): void
    {
        $response = $this->actingAs($this->agent)->postJson('/api/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    /**
     * Admin can edit user
     */
    public function test_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/users/'.$this->agent->id.'/edit');

        $response->assertStatus(200);
    }

    /**
     * Agent cannot edit user
     */
    public function test_agent_cannot_view_edit_form(): void
    {
        $response = $this->actingAs($this->agent)->get('/users/'.$this->admin->id.'/edit');

        $response->assertStatus(403);
    }

    /**
     * Admin can update user
     */
    public function test_admin_can_update_user(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->patch('/users/'.$this->agent->id, [
            'name' => 'Updated Agent',
            'email' => $this->agent->email,
            'role' => 'agent',
        ]);

        $response->assertStatus(302);
        $this->agent->refresh();
        $this->assertEquals('Updated Agent', $this->agent->name);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->agent->id,
            'type' => 'account_updated',
            'title' => 'Administrator zmienił dane Twojego konta',
        ]);
        Mail::assertQueued(AgentAccountUpdatedNotification::class, function (AgentAccountUpdatedNotification $mail) {
            return $mail->hasTo($this->agent->email)
                && isset($mail->changes['Imie i nazwisko'])
                && $mail->passwordChanged === false;
        });
    }

    public function test_admin_password_change_notifies_agent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->patch('/users/'.$this->agent->id, [
            'name' => $this->agent->name,
            'email' => $this->agent->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'role' => 'agent',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->agent->id,
            'type' => 'account_updated',
            'body' => 'Hasło zostało zmienione',
        ]);
        Mail::assertQueued(AgentAccountUpdatedNotification::class, function (AgentAccountUpdatedNotification $mail) {
            return $mail->hasTo($this->agent->email)
                && $mail->changes === []
                && $mail->passwordChanged === true;
        });
    }

    public function test_admin_json_update_notifies_agent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->patchJson('/api/users/'.$this->agent->id, [
            'name' => 'API Updated Agent',
            'email' => $this->agent->email,
            'role' => 'agent',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->agent->id,
            'type' => 'account_updated',
        ]);
        Mail::assertQueued(AgentAccountUpdatedNotification::class, fn (AgentAccountUpdatedNotification $mail) => $mail->hasTo($this->agent->email));
    }

    /**
     * Admin can delete user
     */
    public function test_admin_can_delete_user(): void
    {
        $userToDelete = User::factory()->agent()->create();

        $response = $this->actingAs($this->admin)->delete('/users/'.$userToDelete->id);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
        ]);
    }

    /**
     * Admin cannot delete themselves
     */
    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->delete('/users/'.$this->admin->id);

        $response->assertStatus(403);
    }

    /**
     * Agent cannot delete user
     */
    public function test_agent_cannot_delete_user(): void
    {
        $response = $this->actingAs($this->agent)->delete('/users/'.$this->admin->id);

        $response->assertStatus(403);
    }
}
