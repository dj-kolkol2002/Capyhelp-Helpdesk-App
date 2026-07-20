<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_ticket_report_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['name' => 'Agent Raportowy']);

        Ticket::factory()->count(3)->create([
            'assignee' => $agent->id,
            'status' => 'open',
            'priority' => 'urgent',
            'channel' => 'email',
        ]);

        $response = $this->actingAs($admin)->get('/reports/tickets.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_agent_cannot_download_ticket_report_pdf(): void
    {
        $agent = User::factory()->agent()->create();

        $response = $this->actingAs($agent)->get('/reports/tickets.pdf');

        $response->assertForbidden();
    }
}
