<?php

namespace App\Http\Controllers;

use App\Services\ClamAvScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountSettingsController extends Controller
{
    private const NOTIFICATION_DEFAULTS = [
        'newTicket' => true,
        'assignedTicket' => true,
        'ticketMessage' => true,
        'ticketUpdated' => true,
        'teamChat' => true,
        'accountCreated' => true,
        'accountUpdated' => true,
        'slaWarning' => true,
        'weeklyReport' => false,
    ];

    public function update(Request $request, ClamAvScanner $scanner): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales.supported')))],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('avatar')) {
            $scanner->assertClean($request->file('avatar'), 'avatar');
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'locale' => $validated['locale'],
        ];

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $userData['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $userData['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($userData);
        $request->session()->put('locale', $validated['locale']);
        app()->setLocale($validated['locale']);

        return redirect()
            ->route('settings.index')
            ->with('status', __('helpdesk.account.saved'));
    }

    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'notifications' => ['required', 'array'],
            'notifications.newTicket' => ['required', 'boolean'],
            'notifications.assignedTicket' => ['required', 'boolean'],
            'notifications.ticketMessage' => ['required', 'boolean'],
            'notifications.ticketUpdated' => ['required', 'boolean'],
            'notifications.teamChat' => ['required', 'boolean'],
            'notifications.accountCreated' => ['required', 'boolean'],
            'notifications.accountUpdated' => ['required', 'boolean'],
            'notifications.slaWarning' => ['required', 'boolean'],
            'notifications.weeklyReport' => ['required', 'boolean'],
        ]);

        $preferences = array_merge(
            self::NOTIFICATION_DEFAULTS,
            array_intersect_key($validated['notifications'], self::NOTIFICATION_DEFAULTS),
        );

        $request->user()->update([
            'notification_preferences' => $preferences,
        ]);

        return response()->json([
            'notifications' => $preferences,
            'message' => __('helpdesk.notifications.saved'),
        ]);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales.supported')))],
        ]);

        $request->user()->update([
            'locale' => $validated['locale'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        app()->setLocale($validated['locale']);

        return response()->json([
            'locale' => $validated['locale'],
            'message' => __('helpdesk.locale.saved'),
        ]);
    }
}
