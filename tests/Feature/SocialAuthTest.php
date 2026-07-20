<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_redirect_to_google_provider(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost:8010/auth/google/callback',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth'));

        $socialite = Mockery::mock(SocialiteFactory::class);
        $socialite->shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $socialite);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect('https://accounts.google.com/oauth');
    }

    public function test_redirect_to_unconfigured_provider_returns_to_login(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $socialite = Mockery::mock(SocialiteFactory::class);
        $socialite->shouldNotReceive('driver');

        $this->app->instance(SocialiteFactory::class, $socialite);

        $response = $this->get('/auth/google/redirect');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Logowanie przez Google nie jest jeszcze skonfigurowane.');
    }

    public function test_callback_creates_user_and_social_account(): void
    {
        config(['services.social_auth.allowed_domains' => ['example.com']]);

        $this->mockSocialiteUser('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Google User',
            'email' => 'google@example.com',
            'avatar' => 'https://example.com/google.png',
        ]));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Google User',
            'email' => 'google@example.com',
            'role' => 'agent',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-123',
            'email' => 'google@example.com',
            'avatar' => 'https://example.com/google.png',
        ]);
    }

    public function test_callback_rejects_new_social_user_without_allowlist(): void
    {
        $this->mockSocialiteUser('google', SocialiteUser::fake([
            'id' => 'google-999',
            'name' => 'External User',
            'email' => 'external@example.org',
        ]));

        $this->get('/auth/google/callback')->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'external@example.org',
        ]);
    }

    public function test_callback_links_existing_user_by_email(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'existing@example.com',
        ]);

        $this->mockSocialiteUser('facebook', SocialiteUser::fake([
            'id' => 'facebook-123',
            'name' => 'Existing Facebook User',
            'email' => 'existing@example.com',
        ]));

        $this->get('/auth/facebook/callback')->assertRedirect(route('dashboard'));

        $this->assertSame($user->id, Auth::id());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'facebook',
            'provider_id' => 'facebook-123',
        ]);
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $this->get('/auth/github/redirect')->assertNotFound();
        $this->get('/auth/github/callback')->assertNotFound();
    }

    private function mockSocialiteUser(string $providerName, SocialiteUser $user): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($user);

        $socialite = Mockery::mock(SocialiteFactory::class);
        $socialite->shouldReceive('driver')
            ->with($providerName)
            ->once()
            ->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $socialite);
    }
}
