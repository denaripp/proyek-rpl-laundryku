<?php

namespace Tests\Feature;

use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    public function test_customer_registration_initiates_otp_challenge(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Customer',
            'channel' => 'email',
            'destination' => 'budi.customer@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Budi Customer',
                    'channel' => 'email',
                    'destination' => 'budi.customer@example.com',
                    'status' => 'pending_verification',
                ],
            ]);

        $userId = $response->json('data.user_id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'budi.customer@example.com',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('otp_challenges', [
            'user_id' => $userId,
            'channel' => 'email',
            'consumed_at' => null,
        ]);
    }

    public function test_registration_with_existing_active_user_returns_422(): void
    {
        User::factory()->create([
            'email' => 'active.user@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplikat User',
            'channel' => 'email',
            'destination' => 'active.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_ALREADY_EXISTS',
                ],
            ]);
    }

    public function test_otp_verification_with_wrong_code_fails(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Testing User',
            'channel' => 'email',
            'destination' => 'testing.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $userId = $regResponse->json('data.user_id');

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'otp' => '000000',
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                ],
            ]);
    }

    public function test_otp_verification_with_expired_code_fails(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Expired User',
            'channel' => 'email',
            'destination' => 'expired.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $userId = $regResponse->json('data.user_id');

        // Set expires_at in the past
        OtpChallenge::where('user_id', $userId)->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'otp' => '123456',
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_EXPIRED',
                ],
            ]);
    }

    public function test_resend_otp_invalidates_previous_challenge(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Resend User',
            'channel' => 'email',
            'destination' => 'resend.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $userId = $regResponse->json('data.user_id');
        $firstChallenge = OtpChallenge::where('user_id', $userId)->first();

        $resendResponse = $this->postJson('/api/v1/auth/otp/send', [
            'user_id' => $userId,
            'channel' => 'email',
        ]);

        $resendResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $firstChallenge->refresh();
        $this->assertNotNull($firstChallenge->consumed_at);

        $challenges = OtpChallenge::where('user_id', $userId)->get();
        $this->assertCount(2, $challenges);
    }

    public function test_successful_otp_verification_activates_user_and_issues_token(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Valid User',
            'channel' => 'email',
            'destination' => 'valid.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $userId = $regResponse->json('data.user_id');
        $this->assertNotNull($userId);

        // Update challenge with known OTP to verify verification logic
        $plainOtp = '123456';
        $challenge = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->latest('id')->first();
        $this->assertNotNull($challenge);
        $challenge->update(['otp_hash' => hash('sha256', $plainOtp)]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'otp' => $plainOtp,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'status', 'roles'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertEquals('active', $verifyResponse->json('data.user.status'));

        $user = User::find($userId);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        // Can now login with password
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'valid.user@example.com',
            'password' => 'secret12345',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
