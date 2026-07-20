<?php

namespace App\Http\Controllers;

use App\Mail\AgentAccountCreatedNotification;
use App\Mail\AgentAccountUpdatedNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of agents.
     */
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::where('role', 'agent')->paginate(15);

        return view('users.index', [
            'users' => $users,
        ]);
    }

    /**
     * Get all team members as JSON (for Vue.js).
     */
    public function list(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->whereIn('role', ['admin', 'agent'])
            ->select('id', 'name', 'email', 'role', 'avatar_path', 'created_at')
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,agent',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        $this->notifyAgentAboutAccountCreation($user);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,agent',
        ]);

        $previousValues = $user->only(['name', 'email', 'role']);
        $wasAgent = $user->isAgent();
        $passwordChanged = filled($validated['password'] ?? null);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if ($passwordChanged) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $this->notifyAgentAboutAccountUpdate($user->fresh(), $previousValues, $wasAgent, $passwordChanged);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Delete the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Store user from JSON (for Vue.js).
     */
    public function storeJson(): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,agent',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        $this->notifyAgentAboutAccountCreation($user);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Update user from JSON (for Vue.js).
     */
    public function updateJson(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,agent',
        ]);

        $previousValues = $user->only(['name', 'email', 'role']);
        $wasAgent = $user->isAgent();
        $passwordChanged = filled($validated['password'] ?? null);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if ($passwordChanged) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $this->notifyAgentAboutAccountUpdate($user->fresh(), $previousValues, $wasAgent, $passwordChanged);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Delete user from JSON (for Vue.js).
     */
    public function destroyJson(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    private function notifyAgentAboutAccountUpdate(User $user, array $previousValues, bool $wasAgent, bool $passwordChanged): void
    {
        if (! $wasAgent && ! $user->isAgent()) {
            return;
        }

        $changes = $this->trackedAccountChanges($previousValues, $user);

        if ($changes === [] && ! $passwordChanged) {
            return;
        }

        if (! $user->wantsNotification('accountUpdated')) {
            return;
        }

        UserNotification::create([
            'user_id' => $user->id,
            'ticket_id' => null,
            'type' => 'account_updated',
            'title' => 'Administrator zmienił dane Twojego konta',
            'body' => $this->accountUpdateBody($changes, $passwordChanged),
        ]);

        Mail::to($user->email)->queue(new AgentAccountUpdatedNotification($user, $changes, $passwordChanged));
    }

    private function notifyAgentAboutAccountCreation(User $user): void
    {
        if (! $user->isAgent() || ! $user->wantsNotification('accountCreated')) {
            return;
        }

        UserNotification::create([
            'user_id' => $user->id,
            'ticket_id' => null,
            'type' => 'account_created',
            'title' => 'Utworzono Twoje konto',
            'body' => 'Administrator utworzył konto w systemie CAPYHELP dla adresu '.$user->email.'.',
        ]);

        Mail::to($user->email)->queue(new AgentAccountCreatedNotification($user));
    }

    private function trackedAccountChanges(array $previousValues, User $user): array
    {
        $labels = [
            'name' => 'Imie i nazwisko',
            'email' => 'Email',
            'role' => 'Rola',
        ];

        $changes = [];

        foreach ($labels as $field => $label) {
            $from = $previousValues[$field] ?? null;
            $to = $user->{$field};

            if ((string) $from === (string) $to) {
                continue;
            }

            $changes[$label] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        return $changes;
    }

    private function accountUpdateBody(array $changes, bool $passwordChanged): string
    {
        $parts = collect($changes)
            ->map(fn (array $change, string $field): string => $field.': '.($change['from'] ?? '-').' -> '.($change['to'] ?? '-'))
            ->values();

        if ($passwordChanged) {
            $parts->push('Hasło zostało zmienione');
        }

        return $parts->join(', ');
    }
}
