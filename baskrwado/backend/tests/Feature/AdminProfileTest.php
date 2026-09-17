<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_rich_profile_and_manage_private_avatar(): void
    {
        Storage::fake('private');

        AdminUser::create([
            'name' => 'Owner',
            'email' => 'profile@example.com',
            'password' => 'a-strong-password',
            'role' => 'owner',
            'active' => true,
        ]);

        $token = $this->postJson('/api/admin/auth/login', [
            'email' => 'profile@example.com',
            'password' => 'a-strong-password',
        ])->assertOk()->json('token');

        $this->withToken($token)->patchJson('/api/admin/profile', [
            'name' => 'Vijay Owner',
            'phone' => '9000000000',
            'job_title' => 'Operations Lead',
            'department' => 'Operations',
            'timezone' => 'Asia/Kolkata',
            'language' => 'gu',
            'bio' => 'Owns case quality, assignments and escalations.',
        ])->assertOk()
            ->assertJsonPath('user.department', 'Operations')
            ->assertJsonPath('user.language', 'gu')
            ->assertJsonPath('user.bio', 'Owns case quality, assignments and escalations.')
            ->assertJsonPath('user.avatar_present', false);

        $avatar = UploadedFile::fake()->image('avatar.jpg', 400, 400)->size(180);
        $uploaded = $this->withToken($token)->post('/api/admin/profile/avatar', [
            'avatar' => $avatar,
        ], ['Accept' => 'application/json']);

        $uploaded->assertOk()->assertJsonPath('user.avatar_present', true);
        $user = AdminUser::query()->where('email', 'profile@example.com')->firstOrFail();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('private')->assertExists($user->avatar_path);

        $this->withToken($token)->get('/api/admin/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->withToken($token)->deleteJson('/api/admin/profile/avatar')
            ->assertOk()
            ->assertJsonPath('user.avatar_present', false);
        Storage::disk('private')->assertMissing($user->avatar_path);
    }
}
