<?php

namespace Tests\Feature;

use App\Mail\SlaWarningNotification;
use App\Mail\WeeklyReportNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sla_warning_command_notifies_assignee_before_deadline(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-16 12:00:00');

        $agent = User::factory()->agent()->create([
            'notification_preferences' => ['slaWarning' => true],
        ]);
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);

        $this->artisan('helpdesk:sla-warnings')
            ->expectsOutput('Wyslano 1 ostrzezen SLA.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $agent->id,
            'ticket_id' => $ticket->id,
            'type' => 'sla_warning',
            'title' => 'SLA: wymagana reakcja w '.$ticket->number,
        ]);
        Mail::assertQueued(SlaWarningNotification::class, function (SlaWarningNotification $mail) use ($agent, $ticket) {
            return $mail->hasTo($agent->email)
                && $mail->ticket->is($ticket)
                && $mail->dueAtLabel === '2026-07-16 12:15';
        });
        $this->assertNotNull($ticket->refresh()->sla_warning_sent_at);
    }

    public function test_sla_warning_command_respects_user_preference(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-16 12:00:00');

        $agent = User::factory()->agent()->create([
            'notification_preferences' => ['slaWarning' => false],
        ]);
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);

        $this->artisan('helpdesk:sla-warnings')
            ->expectsOutput('Wyslano 0 ostrzezen SLA.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $agent->id,
            'ticket_id' => $ticket->id,
            'type' => 'sla_warning',
        ]);
        Mail::assertNothingQueued();
        $this->assertNull($ticket->refresh()->sla_warning_sent_at);
    }

    public function test_sla_warning_command_skips_tickets_with_agent_response(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-16 12:00:00');

        $agent = User::factory()->agent()->create([
            'notification_preferences' => ['slaWarning' => true],
        ]);
        $ticket = Ticket::factory()->create([
            'assignee' => $agent->id,
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subMinutes(55),
            'updated_at' => now()->subMinutes(55),
        ]);
        $ticket->messages()->create([
            'user_id' => $agent->id,
            'author_name' => $agent->name,
            'author_email' => $agent->email,
            'author_type' => 'agent',
            'body' => 'Pierwsza odpowiedz',
        ]);

        $this->artisan('helpdesk:sla-warnings')
            ->expectsOutput('Wyslano 0 ostrzezen SLA.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $agent->id,
            'ticket_id' => $ticket->id,
            'type' => 'sla_warning',
        ]);
        Mail::assertNothingQueued();
    }

    public function test_weekly_report_command_sends_to_users_who_enabled_reports(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-16 12:00:00');

        $admin = User::factory()->admin()->create([
            'notification_preferences' => ['weeklyReport' => true],
        ]);
        $agent = User::factory()->agent()->create([
            'notification_preferences' => ['weeklyReport' => false],
        ]);

        Ticket::factory()->create([
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subWeek()->startOfWeek()->addDay(),
            'updated_at' => now()->subWeek()->startOfWeek()->addDay(),
        ]);
        Ticket::factory()->create([
            'status' => 'closed',
            'priority' => 'low',
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeek()->startOfWeek()->addDays(2),
        ]);

        $this->artisan('helpdesk:weekly-report')
            ->expectsOutput('Wyslano 1 raportow tygodniowych.')
            ->assertExitCode(0);

        Mail::assertQueued(WeeklyReportNotification::class, function (WeeklyReportNotification $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && $mail->report['range_label'] === '2026-07-06 - 2026-07-12'
                && $mail->report['kpis'][0]['value'] === 1
                && $mail->report['kpis'][1]['value'] === 1;
        });
        Mail::assertNotQueued(WeeklyReportNotification::class, fn (WeeklyReportNotification $mail) => $mail->hasTo($agent->email));
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'weekly_report',
            'title' => 'Tygodniowy raport CAPYHELP',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $agent->id,
            'type' => 'weekly_report',
        ]);
    }
}
