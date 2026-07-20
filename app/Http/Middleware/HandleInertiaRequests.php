<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? $request->user()->only('id', 'name', 'email', 'role', 'avatar_url', 'notification_preferences', 'locale')
                    : null,
            ],
            'localization' => [
                'locale' => App::currentLocale(),
                'supported' => config('locales.supported'),
            ],
            'socialAuth' => [
                'google' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
                'facebook' => filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret')),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
