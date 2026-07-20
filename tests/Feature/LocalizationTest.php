<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_is_shared_with_inertia(): void
    {
        $user = User::factory()->create([
            'locale' => 'fr',
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.locale', 'fr')
                ->where('localization.locale', 'fr')
                ->where('localization.supported.fr', 'Français')
            );

        $this->assertSame('fr', App::currentLocale());
    }

    public function test_account_locale_must_be_supported(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings')
            ->patch('/settings/account', [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'es',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHasErrors('locale');
    }

    public function test_user_can_update_locale_preference_without_saving_profile(): void
    {
        $user = User::factory()->create([
            'locale' => 'pl',
        ]);

        $this->actingAs($user)
            ->patchJson('/settings/locale', [
                'locale' => 'de',
            ])
            ->assertOk()
            ->assertJson([
                'locale' => 'de',
            ]);

        $this->assertSame('de', $user->fresh()->locale);
        $this->assertSame('de', session('locale'));
    }

    public function test_guest_can_switch_locale_in_session(): void
    {
        $this->from('/')
            ->get('/locale/de')
            ->assertRedirect('/');

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.locale', 'de')
            );
    }
}
