<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);

        $this->user = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '+6281234567890',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => 1,
            'status' => 'active',
        ]);

        UserProfile::create([
            'user_id' => $this->user->id,
        ]);

        $this->otherUser = User::create([
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
            'phone' => '+6281234567891',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->otherUser->id,
            'role_id' => 1,
            'status' => 'active',
        ]);
    }

    public function test_upload_valid_avatar_stores_file_and_updates_avatar_url(): void
    {
        // Create 300x300 fake image (valid dimension >= 256x256)
        $file = UploadedFile::fake()->image('avatar.png', 300, 300);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_photo', true);

        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotEmpty($avatarUrl);
        $this->assertStringContainsString('storage/avatars/avatar_', $avatarUrl);
        $this->assertStringContainsString('?v=', $avatarUrl);

        // Verify /me endpoint returns the updated avatar_url
        $meRes = $this->actingAs($this->user)->getJson('/api/v1/me');
        $meRes->assertStatus(200)
            ->assertJsonPath('data.avatar_url', $avatarUrl);

        // Verify file stored in public disk
        $profile = $this->user->fresh()->profile;
        $this->assertNotNull($profile->profile_photo_key);
        Storage::disk('public')->assertExists($profile->profile_photo_key);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_oversized_files_greater_than_2mb(): void
    {
        // 2500 KB > 2048 KB
        $file = UploadedFile::fake()->image('large.jpg', 600, 600)->size(2500);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_images_smaller_than_256x256(): void
    {
        // 100x100 is too small
        $file = UploadedFile::fake()->image('small.jpg', 100, 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'IMAGE_DIMENSION_TOO_SMALL');
    }

    public function test_delete_avatar_removes_file_and_resets_url(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);
        $this->actingAs($this->user)->postJson('/api/v1/profile/photo', ['photo' => $file]);

        $profile = $this->user->fresh()->profile;
        $storedKey = $profile->profile_photo_key;
        Storage::disk('public')->assertExists($storedKey);

        $delResponse = $this->actingAs($this->user)->deleteJson('/api/v1/profile/photo');
        $delResponse->assertStatus(200)
            ->assertJsonPath('data.has_photo', false)
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('public')->assertMissing($storedKey);

        // Verify /me has null avatar_url
        $meRes = $this->actingAs($this->user)->getJson('/api/v1/me');
        $meRes->assertJsonPath('data.avatar_url', null);
    }

    public function test_user_cannot_access_or_alter_avatar_unauthenticated(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);
        $response = $this->postJson('/api/v1/profile/photo', ['photo' => $file]);
        $response->assertStatus(401);
    }
}
