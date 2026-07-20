<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_profile_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $response = $this->actingAs($user)->patch('/settings/account', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'locale' => 'de',
            'avatar' => $this->avatarUpload(),
        ]);

        $response->assertRedirect('/settings');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertSame('de', $user->locale);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_user_can_remove_profile_avatar(): void
    {
        Storage::fake('public');

        $path = $this->avatarUpload()->store('avatars', 'public');
        $user = User::factory()->create(['avatar_path' => $path]);

        $response = $this->actingAs($user)->patch('/settings/account', [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'pl',
            'remove_avatar' => '1',
        ]);

        $response->assertRedirect('/settings');

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings/notifications', [
            'notifications' => [
                'newTicket' => false,
                'assignedTicket' => true,
                'ticketMessage' => false,
                'ticketUpdated' => true,
                'teamChat' => false,
                'accountCreated' => true,
                'accountUpdated' => false,
                'slaWarning' => false,
                'weeklyReport' => true,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('notifications.newTicket', false)
            ->assertJsonPath('notifications.assignedTicket', true)
            ->assertJsonPath('notifications.ticketMessage', false)
            ->assertJsonPath('notifications.ticketUpdated', true)
            ->assertJsonPath('notifications.teamChat', false)
            ->assertJsonPath('notifications.accountCreated', true)
            ->assertJsonPath('notifications.accountUpdated', false)
            ->assertJsonPath('notifications.slaWarning', false)
            ->assertJsonPath('notifications.weeklyReport', true);

        $user->refresh();

        $this->assertSame([
            'newTicket' => false,
            'assignedTicket' => true,
            'ticketMessage' => false,
            'ticketUpdated' => true,
            'teamChat' => false,
            'accountCreated' => true,
            'accountUpdated' => false,
            'slaWarning' => false,
            'weeklyReport' => true,
        ], $user->notification_preferences);
    }

    public function test_notification_preferences_require_all_options(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings/notifications', [
            'notifications' => [
                'newTicket' => true,
            ],
        ]);

        $response->assertUnprocessable();
    }

    private function avatarUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar_').'.png';

        File::put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, 'avatar.png', 'image/png', null, true);
    }
}
