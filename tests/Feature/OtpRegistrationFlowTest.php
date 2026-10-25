<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

        // Advance simulated time past 60s cooldown
        $this->travel(70)->seconds();

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

    public function test_registration_with_phone_stores_phone_and_authenticates_successfully(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'User Terisi Phone',
            'email' => 'user.terisi.phone@example.com',
            'phone' => '081234567899',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $regResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'User Terisi Phone',
                    'destination' => 'user.terisi.phone@example.com',
                    'status' => 'pending_verification',
                ],
            ]);

        $userId = $regResponse->json('data.user_id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'user.terisi.phone@example.com',
            'phone' => '081234567899',
            'role' => 'customer',
            'is_active' => false,
        ]);

        $challenge = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->latest('id')->first();
        $plainOtp = '654321';
        $challenge->update(['otp_hash' => hash('sha256', $plainOtp)]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'otp' => $plainOtp,
        ]);
        $verifyResponse->assertStatus(200);

        // Login via email
        $loginEmailResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'user.terisi.phone@example.com',
            'password' => 'secret12345',
        ]);
        $loginEmailResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'user.terisi.phone@example.com',
                        'phone' => '081234567899',
                        'status' => 'active',
                    ],
                ],
            ]);

        // Login via phone
        $loginPhoneResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => '081234567899',
            'password' => 'secret12345',
        ]);
        $loginPhoneResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'user.terisi.phone@example.com',
                        'phone' => '081234567899',
                        'status' => 'active',
                    ],
                ],
            ]);
    }

    public function test_registration_with_empty_phone_succeeds_and_authenticates(): void
    {
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'User Phone Kosong',
            'email' => 'user.phone.kosong@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $regResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'User Phone Kosong',
                    'status' => 'pending_verification',
                ],
            ]);

        $userId = $regResponse->json('data.user_id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'user.phone.kosong@example.com',
            'phone' => null,
            'role' => 'customer',
            'is_active' => false,
        ]);

        $challenge = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->latest('id')->first();
        $plainOtp = '112233';
        $challenge->update(['otp_hash' => hash('sha256', $plainOtp)]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'otp' => $plainOtp,
        ]);
        $verifyResponse->assertStatus(200);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'user.phone.kosong@example.com',
            'password' => 'secret12345',
        ]);
        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'user.phone.kosong@example.com',
                        'phone' => null,
                        'status' => 'active',
                    ],
                ],
            ]);
    }

    public function test_email_registration_dispatches_otp_mail_via_mail_fake(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mail Fake User',
            'channel' => 'email',
            'destination' => 'mailfake.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel' => 'email',
                    'otp_sent' => true,
                    'delivery_status' => 'sent',
                ],
            ]);

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasTo('mailfake.user@example.com')
                && strlen($mail->otpCode) === 6;
        });
    }

    public function test_whatsapp_registration_dispatches_http_post_to_fonnte_via_http_fake(): void
    {
        config(['services.fonnte.api_token' => 'mock-token-xyz']);
        Http::fake([
            'https://api.fonnte.com/*' => Http::response([
                'status' => true,
                'target' => ['6281234567800'],
                'id' => ['msg-12345'],
                'process' => 'sent',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Fonnte Fake User',
            'channel' => 'whatsapp',
            'destination' => '081234567800',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel' => 'whatsapp',
                    'otp_sent' => true,
                    'delivery_status' => 'sent',
                ],
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.fonnte.com/send')
                && $request->header('Authorization')[0] === 'mock-token-xyz'
                && $request['target'] === '6281234567800'
                && str_contains($request['message'], 'LaundryKu');
        });
    }

    public function test_debug_otp_is_null_when_otp_debug_response_is_false(): void
    {
        config(['services.otp.debug_response' => false]);
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Debug Otp User',
            'channel' => 'email',
            'destination' => 'nodebug@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.debug_otp'));
    }

    public function test_debug_otp_is_present_when_otp_debug_response_is_true_in_testing(): void
    {
        config(['services.otp.debug_response' => true]);
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'With Debug Otp User',
            'channel' => 'email',
            'destination' => 'withdebug@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.debug_otp'));
        $this->assertSame(6, strlen($response->json('data.debug_otp')));
    }

    public function test_registration_rolls_back_if_whatsapp_gateway_fails(): void
    {
        config(['services.fonnte.api_token' => 'mock-token-xyz']);
        Http::fake([
            'https://api.fonnte.com/*' => Http::response([
                'status' => false,
                'reason' => 'device disconnected',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Rollback User',
            'channel' => 'whatsapp',
            'destination' => '081299998888',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_DELIVERY_FAILED',
                ],
            ]);

        // User should NOT be created in database because transaction was rolled back
        $this->assertDatabaseMissing('users', [
            'phone' => '6281299998888',
        ]);
    }

    public function test_resend_cooldown_enforced_within_60_seconds(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cooldown Test User',
            'channel' => 'email',
            'destination' => 'cooldown.user@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);
        $regResponse->assertStatus(201);
        $userId = $regResponse->json('data.user_id');

        // Immediate resend without waiting 60s
        $resendResponse = $this->postJson('/api/v1/auth/otp/send', [
            'user_id' => $userId,
            'channel' => 'email',
        ]);

        $resendResponse->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_COOLDOWN',
                ],
            ]);
    }

    public function test_rate_limiter_blocks_excessive_otp_requests(): void
    {
        Mail::fake();

        // Exhaust limit for specific destination directly in RateLimiter
        $destKey = 'otp-reg-dest:' . hash('sha256', 'target.spam@example.com');
        RateLimiter::clear($destKey);
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($destKey, 3600);
        }

        $blockedResp = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson('/api/v1/auth/register', [
                'name' => 'Spammer User',
                'channel' => 'email',
                'destination' => 'target.spam@example.com',
                'password' => 'secret12345',
                'password_confirmation' => 'secret12345',
            ]);

        $blockedResp->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ],
            ]);
    }

}


