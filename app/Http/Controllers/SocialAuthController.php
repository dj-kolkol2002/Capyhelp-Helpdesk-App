<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        if (! $this->isProviderConfigured($provider)) {
            return redirect()
                ->route('login')
                ->with('status', 'Logowanie przez '.$this->providerLabel($provider).' nie jest jeszcze skonfigurowane.');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)->user();
        $user = $this->findOrCreateUser($provider, $socialUser);

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function findOrCreateUser(string $provider, SocialiteUser $socialUser): User
    {
        abort_if(blank($socialUser->getEmail()), 422, 'Provider did not return an email address.');

        $email = $socialUser->getEmail();
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account) {
            $this->syncSocialAccount($account, $socialUser);

            return $account->user;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            abort_unless($this->isAllowedNewSocialUser($email), 403, 'Ten adres e-mail nie ma dostępu do logowania społecznościowego.');

            $user = User::query()->create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Helpdesk User',
                'password' => Hash::make(Str::random(32)),
                'email' => $email,
                'email_verified_at' => now(),
                'role' => 'agent',
            ]);
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'email' => $email,
            'avatar' => $socialUser->getAvatar(),
        ]);

        return $user;
    }

    private function isProviderConfigured(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"))
            && filled(config("services.{$provider}.redirect"));
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            default => $provider,
        };
    }

    private function isAllowedNewSocialUser(string $email): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $domain = Str::after($normalizedEmail, '@');
        $allowedEmails = collect(config('services.social_auth.allowed_emails', []))
            ->map(fn (string $allowedEmail): string => mb_strtolower(trim($allowedEmail)));
        $allowedDomains = collect(config('services.social_auth.allowed_domains', []))
            ->map(fn (string $allowedDomain): string => ltrim(mb_strtolower(trim($allowedDomain)), '@'));

        return $allowedEmails->contains($normalizedEmail) || $allowedDomains->contains($domain);
    }

    private function syncSocialAccount(SocialAccount $account, SocialiteUser $socialUser): void
    {
        $account->update([
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
        ]);
    }
}
