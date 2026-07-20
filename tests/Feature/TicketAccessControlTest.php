<?php

namespace Tests\Feature;

use App\Mail\NewTicketNotification;
use App\Mail\TicketAssignedNotification;
use App\Mail\TicketUpdatedNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $otherAgent;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create();
        $this->otherAgent = User::factory()->agent()->create();

        // Create a ticket assigned to the first agent
        $this->ticket = Ticket::factory()->create([
            'assignee' => $this->agent->id,
        ]);
    }

    /**
     * Admin can access tickets list
     */
    public function test_admin_can_view_tickets_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/tickets');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('tickets')
            );
    }

    /**
     * Agent can access their ticket queue
     */
    public function test_agent_can_view_tickets_list(): void
    {
        $response = $this->actingAs($this->agent)->get('/tickets');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('initialView', 'Tickets')
                ->has('tickets')
            );
    }

    /**
     * Admin can create ticket
     */
    public function test_admin_can_create_ticket(): void
    {
        $response = $this->actingAs($this->admin)->get('/tickets/create');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tickets/Form')
                ->where('mode', 'create')
                ->has('agents')
            );
    }

    /**
     * Agent cannot create ticket
     */
    public function test_agent_cannot_create_ticket(): void
    {
        $response = $this->actingAs($this->agent)->get('/tickets/create');

        $response->assertStatus(403);
    }

    /**
     * Admin can store ticket
     */
    public function test_admin_can_store_ticket(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->post('/tickets', [
            'requester_name' => 'John Doe',
            'requester_email' => 'john@example.com',
            'subject' => 'Test Issue',
            'priority' => 'medium',
            'channel' => 'email',
            'assignee' => $this->agent->id,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('tickets', [
            'requester_name' => 'John Doe',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->admin->id,
            'type' => 'new_ticket',
            'title' => 'Nowe zgłoszenie TKT-000002',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->agent->id,
            'type' => 'ticket_assigned',
        ]);
        Mail::assertQueued(NewTicketNotification::class, fn (NewTicketNotification $mail) => $mail->hasTo($this->admin->email));
        Mail::assertQueued(NewTicketNotification::class, fn (NewTicketNotification $mail) => $mail->hasTo($this->agent->email));
        Mail::assertQueued(NewTicketNotification::class, fn (NewTicketNotification $mail) => $mail->hasTo($this->otherAgent->email));
        Mail::assertQueued(TicketAssignedNotification::class, function (TicketAssignedNotification $mail) {
            return $mail->hasTo($this->agent->email)
                && $mail->ticket->requester_email === 'john@example.com';
        });
    }

    public function test_ticket_notifications_respect_user_preferences(): void
    {
        Mail::fake();

        $this->admin->update([
            'notification_preferences' => [
                'newTicket' => false,
                'assignedTicket' => true,
                'slaWarning' => true,
                'weeklyReport' => false,
            ],
        ]);

        $this->agent->update([
            'notification_preferences' => [
                'newTicket' => true,
                'assignedTicket' => false,
                'slaWarning' => true,
                'weeklyReport' => false,
            ],
        ]);

        $this->actingAs($this->admin)->post('/tickets', [
            'requester_name' => 'John Doe',
            'requester_email' => 'john@example.com',
            'subject' => 'Test Issue',
            'priority' => 'medium',
            'channel' => 'email',
            'assignee' => $this->agent->id,
        ]);

        Mail::assertNotQueued(NewTicketNotification::class, function (NewTicketNotification $mail) {
            return $mail->hasTo($this->admin->email);
        });
        Mail::assertQueued(NewTicketNotification::class, function (NewTicketNotification $mail) {
            return $mail->hasTo($this->agent->email);
        });
        Mail::assertNotQueued(TicketAssignedNotification::class);
    }

    public function test_assignment_change_sends_email_to_new_assignee(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->patch('/tickets/'.$this->ticket->id, [
            'assignee' => $this->otherAgent->id,
        ]);

        $response->assertStatus(302);
        Mail::assertQueued(TicketAssignedNotification::class, function (TicketAssignedNotification $mail) {
            return $mail->hasTo($this->otherAgent->email)
                && $mail->ticket->id === $this->ticket->id;
        });
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->otherAgent->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_assigned',
        ]);
    }

    public function test_ticket_update_sends_app_and_email_notifications(): void
    {
        Mail::fake();
        $this->ticket->update([
            'status' => 'open',
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->admin)->patch('/tickets/'.$this->ticket->id, [
            'status' => 'resolved',
            'priority' => 'urgent',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->agent->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_updated',
            'title' => 'Zmieniono zgłoszenie '.$this->ticket->number,
        ]);
        Mail::assertQueued(TicketUpdatedNotification::class, function (TicketUpdatedNotification $mail) {
            return $mail->hasTo($this->ticket->requester_email)
                && $mail->ticket->id === $this->ticket->id
                && isset($mail->changes['Status'], $mail->changes['Priorytet']);
        });
        Mail::assertQueued(TicketUpdatedNotification::class, function (TicketUpdatedNotification $mail) {
            return $mail->hasTo($this->agent->email)
                && $mail->ticket->id === $this->ticket->id;
        });
    }

    public function test_user_can_mark_app_notification_as_read(): void
    {
        $notification = UserNotification::create([
            'user_id' => $this->agent->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_assigned',
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $response = $this->actingAs($this->agent)->patchJson("/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_app_notifications_as_read(): void
    {
        UserNotification::create([
            'user_id' => $this->agent->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_assigned',
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $response = $this->actingAs($this->agent)->patchJson('/notifications/read-all');

        $response->assertOk();
        $this->assertSame(0, $this->agent->appNotifications()->whereNull('read_at')->count());
    }

    /**
     * Agent can view assigned ticket
     */
    public function test_agent_can_view_assigned_ticket(): void
    {
        $response = $this->actingAs($this->agent)->get('/tickets/'.$this->ticket->id);

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tickets/Show')
                ->where('ticket.id', $this->ticket->id)
            );
    }

    public function test_agent_dashboard_only_contains_assigned_tickets(): void
    {
        $assignedTicket = Ticket::factory()->create([
            'assignee' => $this->agent->id,
            'subject' => 'Assigned dashboard ticket',
        ]);

        $otherTicket = Ticket::factory()->create([
            'assignee' => $this->otherAgent->id,
            'subject' => 'Other agent dashboard ticket',
        ]);

        $response = $this->actingAs($this->agent)->get('/tickets');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('initialView', 'Tickets')
                ->has('tickets')
            );
        $response->assertSee($assignedTicket->subject);
        $response->assertDontSee($otherTicket->subject);
    }

    /**
     * Other agent cannot view unassigned ticket
     */
    public function test_agent_cannot_view_unassigned_ticket(): void
    {
        $unassignedTicket = Ticket::factory()->create([
            'assignee' => null,
        ]);

        $response = $this->actingAs($this->otherAgent)->get('/tickets/'.$unassignedTicket->id);

        $response->assertStatus(403);
    }

    /**
     * Admin can edit any ticket
     */
    public function test_admin_can_edit_ticket(): void
    {
        $response = $this->actingAs($this->admin)->get('/tickets/'.$this->ticket->id.'/edit');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tickets/Form')
                ->where('mode', 'edit')
                ->where('ticket.id', $this->ticket->id)
                ->has('agents')
            );
    }

    /**
     * Assigned agent can update their ticket
     */
    public function test_agent_can_update_assigned_ticket(): void
    {
        $response = $this->actingAs($this->agent)->patch('/tickets/'.$this->ticket->id, [
            'status' => 'resolved',
        ]);

        $response->assertStatus(302);
        $this->ticket->refresh();
        $this->assertEquals('resolved', $this->ticket->status);
    }

    /**
     * Other agent cannot update unassigned ticket
     */
    public function test_agent_cannot_update_unassigned_ticket(): void
    {
        $otherTicket = Ticket::factory()->create([
            'assignee' => $this->otherAgent->id,
        ]);

        $response = $this->actingAs($this->agent)->patch('/tickets/'.$otherTicket->id, [
            'status' => 'resolved',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Admin can delete ticket
     */
    public function test_admin_can_delete_ticket(): void
    {
        $response = $this->actingAs($this->admin)->delete('/tickets/'.$this->ticket->id);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('tickets', [
            'id' => $this->ticket->id,
        ]);
    }

    /**
     * Agent cannot delete ticket
     */
    public function test_agent_cannot_delete_ticket(): void
    {
        $response = $this->actingAs($this->agent)->delete('/tickets/'.$this->ticket->id);

        $response->assertStatus(403);
    }

    /**
     * Agent can add message to ticket
     */
    public function test_agent_can_add_message_to_assigned_ticket(): void
    {
        $response = $this->actingAs($this->agent)->post('/tickets/'.$this->ticket->id.'/messages', [
            'body' => 'Test message from agent',
        ]);

        $response->assertStatus(201);
    }
}
