<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSectionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sectionRoutes')]
    public function test_dashboard_sections_have_dedicated_routes(string $uri, string $initialView): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get($uri)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('initialView', $initialView)
            );
    }

    public function test_dashboard_redirects_to_tickets(): void
    {
        $user = User::factory()->agent()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/tickets');
    }

    public static function sectionRoutes(): array
    {
        return [
            ['/tickets', 'Tickets'],
            ['/team-chat', 'TeamChat'],
            ['/knowledge-base', 'KnowledgeBase'],
            ['/agents', 'Agents'],
            ['/reports', 'Reports'],
            ['/settings', 'Settings'],
        ];
    }
}
