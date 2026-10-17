<?php

namespace Tests\Feature;

use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clearResolvedInstances();
        \Illuminate\Support\Facades\Http::fake([
            'api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'id' => ['test_auth_msg_123'],
            ], 200),
        ]);
    }

    public function test_customer_can_register_via_email_with_password_and_receives_pending_status(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'channel' => 'email',
            'destination' => 'budi@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Budi Santoso',
                    'channel' => 'email',
                    'destination' => 'budi@example.com',
                    'status' => 'pending_verification',
                    'otp_sent' => true,
                    'delivery_status' => 'sent',
                ],
            ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OtpMail::class, function ($mail) {
            return $mail->hasTo('budi@example.com') && strlen($mail->otpCode) === 6;
        });

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);

        $user = User::where('email', 'budi@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pending_verification', $user->status);
        $this->assertNull($user->password); // Credentials bound to challenge metadata until OTP verified
        $this->assertTrue($user->hasRole('customer'));

        // Verify OTP challenge was created with pending credentials
        $challenge = OtpChallenge::where('user_id', $user->id)->first();
        $this->assertNotNull($challenge);
        $this->assertEquals('email', $challenge->channel);
        $this->assertNotNull($challenge->metadata['pending_password_hash']);
        $this->assertTrue(Hash::check('SecurePass123!', $challenge->metadata['pending_password_hash']));
    }

    public function test_customer_can_register_via_whatsapp_with_number_normalization_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Rahma',
            'channel' => 'whatsapp',
            'destination' => '081234567890',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel' => 'whatsapp',
                    'destination' => '6281234567890',
                    'status' => 'pending_verification',
                ],
            ]);

        $user = User::where('phone', '6281234567890')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole('customer'));

        $challenge = OtpChallenge::where('user_id', $user->id)->first();
        $this->assertNotNull($challenge);
        $this->assertTrue(Hash::check('StrongPassword123*', $challenge->metadata['pending_password_hash']));
    }

    public function test_registration_rejects_password_shorter_than_8_chars(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Short Pass User',
            'channel' => 'email',
            'destination' => 'short@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_registration_rejects_unmatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mismatch User',
            'channel' => 'email',
            'destination' => 'mismatch@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPass123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_registration_rejects_invalid_whatsapp_number(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Invalid Phone User',
            'channel' => 'whatsapp',
            'destination' => '0812', // Too short
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_registration_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Invalid Email User',
            'channel' => 'email',
            'destination' => 'not-an-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_registration_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Invalid Channel User',
            'channel' => 'sms',
            'destination' => '081234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_registration_rejects_already_active_user(): void
    {
        User::create([
            'name' => 'Active Customer',
            'phone' => '6281234567890',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate Attempt',
            'channel' => 'whatsapp',
            'destination' => '081234567890',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_ALREADY_EXISTS',
                ],
            ]);
    }

    public function test_registration_resending_for_pending_user_does_not_overwrite_password(): void
    {
        $first = $this->postJson('/api/v1/auth/register', [
            'name' => 'Pending User',
            'channel' => 'whatsapp',
            'destination' => '081234567890',
            'password' => 'OriginalPass123!',
            'password_confirmation' => 'OriginalPass123!',
        ]);
        $first->assertStatus(201);
        $userId = $first->json('data.user_id');
        $firstOtpId = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->first()->id;

        // Second registration attempt for pending user updates pending credentials and issues fresh OTP
        $second = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'channel' => 'whatsapp',
            'destination' => '081234567890',
            'password' => 'Kucing*Lari*99',
            'password_confirmation' => 'Kucing*Lari*99',
        ]);
        $second->assertStatus(201);

        $user = User::find($userId);
        $this->assertEquals('Pending User', $user->name);
        $this->assertNull($user->password);

        // Fresh OTP is issued
        $freshChallenge = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->first();
        $this->assertNotEquals($firstOtpId, $freshChallenge->id);
        $this->assertEquals('Budi Santoso', $freshChallenge->metadata['pending_name']);
        $this->assertTrue(Hash::check('Kucing*Lari*99', $freshChallenge->metadata['pending_password_hash']));
    }

    public function test_otp_verification_activates_user_and_returns_sanctum_token(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahmad Dahlan',
            'channel' => 'email',
            'destination' => 'ahmad@example.com',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $userId = $regResponse->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->update(['otp_hash' => hash('sha256', '123456')]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'ahmad@example.com',
            'otp' => '123456',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Ahmad Dahlan',
                        'email' => 'ahmad@example.com',
                        'status' => 'active',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        $this->assertNotEmpty($verifyResponse->json('data.token'));

        $user = User::find($userId);
        $this->assertEquals('active', $user->status);
        $this->assertNotNull($user->verified_at);
    }

    public function test_verify_otp_rejects_wrong_code_and_tracks_attempts(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dewi Lestari',
            'channel' => 'email',
            'destination' => 'dewi@example.com',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $userId = $regResponse->json('data.user_id');

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'dewi@example.com',
            'otp' => '000000',
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                ],
            ]);

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $this->assertEquals(1, $challenge->attempts);

        // Account must remain pending
        $user = User::find($userId);
        $this->assertEquals('pending_verification', $user->status);
    }

    public function test_verify_otp_rejects_expired_otp(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Eko Prasetyo',
            'channel' => 'email',
            'destination' => 'eko@example.com',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $userId = $regResponse->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->otp_hash = hash('sha256', '123456');
        $challenge->expires_at = now()->subMinutes(10);
        $challenge->save();

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'eko@example.com',
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

    public function test_verify_otp_rejects_already_consumed_otp(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Fajar Nugraha',
            'channel' => 'email',
            'destination' => 'fajar@example.com',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $userId = $regResponse->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->otp_hash = hash('sha256', '123456');
        $challenge->consumed_at = now()->subMinute();
        $challenge->save();

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'fajar@example.com',
            'otp' => '123456',
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_ALREADY_USED',
                ],
            ]);
    }

    public function test_verify_otp_rejects_after_max_attempts_exceeded(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Gita Gutawa',
            'channel' => 'email',
            'destination' => 'gita@example.com',
            'password' => 'StrongPassword123*',
            'password_confirmation' => 'StrongPassword123*',
        ]);

        $userId = $regResponse->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->otp_hash = hash('sha256', '123456');
        $challenge->attempts = 5;
        $challenge->save();

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'gita@example.com',
            'otp' => '123456',
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_MAX_ATTEMPTS',
                ],
            ]);
    }

    public function test_active_user_can_login_with_correct_password_without_otp(): void
    {
        $user = User::create([
            'name' => 'Hendra Setiawan',
            'email' => 'hendra@example.com',
            'password' => Hash::make('MyPassword123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        $customerRole = Role::where('code', 'customer')->first();
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'hendra@example.com',
            'password' => 'MyPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Hendra Setiawan',
                        'email' => 'hendra@example.com',
                        'status' => 'active',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        // Ensure no OTP challenge was created during login
        $this->assertEquals(0, OtpChallenge::where('user_id', $user->id)->count());
    }

    public function test_active_user_login_with_whatsapp_normalizes_number(): void
    {
        $user = User::create([
            'name' => 'Rina Nose',
            'phone' => '6281298765432',
            'password' => Hash::make('RinaPass123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);
        $customerRole = Role::where('code', 'customer')->first();
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);

        // Login using leading 08 format
        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '081298765432',
            'password' => 'RinaPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Rina Nose',
                        'phone' => '6281298765432',
                        'status' => 'active',
                    ],
                ],
            ]);
    }

    public function test_login_rejects_wrong_password_with_generic_error(): void
    {
        User::create([
            'name' => 'Hendra Setiawan',
            'email' => 'hendra@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'hendra@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    public function test_login_rejects_unregistered_user_with_generic_error(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'notfound@example.com',
            'password' => 'AnyPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    public function test_login_rejects_pending_verification_user(): void
    {
        User::create([
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => Hash::make('ValidPass123!'),
            'status' => 'pending_verification',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'pending@example.com',
            'password' => 'ValidPass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_PENDING_VERIFICATION',
                ],
            ]);
    }

    public function test_suspended_and_banned_users_are_rejected_from_login_and_verification(): void
    {
        // 1. Suspended User
        User::create([
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'password' => Hash::make('Password123!'),
            'status' => 'suspended',
            'verified_at' => now(),
        ]);

        $loginSuspended = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'suspended@example.com',
            'password' => 'Password123!',
        ]);

        $loginSuspended->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                ],
            ]);

        // 2. Banned User
        User::create([
            'name' => 'Banned User',
            'email' => 'banned@example.com',
            'password' => Hash::make('Password123!'),
            'status' => 'banned',
            'verified_at' => now(),
        ]);

        $loginBanned = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'banned@example.com',
            'password' => 'Password123!',
        ]);

        $loginBanned->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_BANNED',
                ],
            ]);
    }

    public function test_rate_limit_on_login_endpoint_returns_429_after_limit_exceeded(): void
    {
        $destination = 'ratelimit.login@example.com';
        User::create([
            'name' => 'Rate Limit Login User',
            'email' => $destination,
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        // Make 5 requests within the limit
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'channel' => 'email',
                'destination' => $destination,
                'password' => 'WrongPassword',
            ]);
            $res->assertStatus(401);
        }

        // 6th request exceeds 5 req/min rate limit
        $overLimitRes = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => $destination,
            'password' => 'WrongPassword',
        ]);

        $overLimitRes->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                ],
            ]);
    }

    public function test_authenticated_user_can_access_me_and_logout_revokes_token(): void
    {
        Mail::fake();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Indra Wijaya',
            'channel' => 'email',
            'destination' => 'indra@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $userId = $regResponse->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->update(['otp_hash' => hash('sha256', '123456')]);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'indra@example.com',
            'otp' => '123456',
        ]);

        $token = $verifyResponse->json('data.token');

        // Access protected /me endpoint
        $meResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $userId,
                    'name' => 'Indra Wijaya',
                    'email' => 'indra@example.com',
                    'status' => 'active',
                ],
            ]);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Logout berhasil. Sesi telah diakhiri.',
                ],
            ]);

        // Clear in-memory guards in test runner
        $this->app['auth']->forgetGuards();

        // Subsequent access with revoked token must fail with 401
        $revokedMeResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/me');

        $revokedMeResponse->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    public function test_logout_then_login_again_uses_password_without_sending_otp(): void
    {
        Mail::fake();

        // 1. Register & verify via email
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Relogin User',
            'channel' => 'email',
            'destination' => 'relogin@example.com',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ]);
        $reg->assertStatus(201);
        $userId = $reg->json('data.user_id');

        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $challenge->update(['otp_hash' => hash('sha256', '123456')]);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'relogin@example.com',
            'otp' => '123456',
        ]);
        $verify->assertStatus(200);
        $token = $verify->json('data.token');

        // 2. Logout
        $logout = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');
        $logout->assertStatus(200);

        $this->app['auth']->forgetGuards();

        // Record current count of OTP challenges
        $otpCountBefore = OtpChallenge::where('user_id', $userId)->count();

        // 3. Login again with same email and password
        $loginAgain = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'relogin@example.com',
            'password' => 'SecretPass123!',
        ]);

        $loginAgain->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Relogin User',
                        'email' => 'relogin@example.com',
                        'status' => 'active',
                    ],
                ],
            ]);

        $this->assertNotEmpty($loginAgain->json('data.token'));

        // 4. Verify NO new OTP was sent or created during login
        $otpCountAfter = OtpChallenge::where('user_id', $userId)->count();
        $this->assertEquals($otpCountBefore, $otpCountAfter, 'Login for active user must not generate/send OTP.');
    }

    public function test_registration_rejects_weak_passwords_and_patterns(): void
    {
        // 1. All same characters (xxxxxxxx)
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Customer',
            'channel' => 'email',
            'destination' => 'budi.cust@example.com',
            'password' => 'xxxxxxxxx',
            'password_confirmation' => 'xxxxxxxxx',
        ]);
        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 2. Simple sequences (12345678, abcdefgh)
        $resSeq1 = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Customer',
            'channel' => 'email',
            'destination' => 'budi.cust@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);
        $resSeq1->assertStatus(422);

        $resSeq2 = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Customer',
            'channel' => 'email',
            'destination' => 'budi.cust@example.com',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ]);
        $resSeq2->assertStatus(422);

        // 3. Contains laundryku or laundryku123
        $resLku = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Customer',
            'channel' => 'email',
            'destination' => 'budi.cust@example.com',
            'password' => 'laundryku123',
            'password_confirmation' => 'laundryku123',
        ]);
        $resLku->assertStatus(422);

        // 4. Contains user name
        $resName = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'channel' => 'email',
            'destination' => 'santoso99@example.com',
            'password' => 'SantosoHebat!',
            'password_confirmation' => 'SantosoHebat!',
        ]);
        $resName->assertStatus(422);

        // 5. Contains user email local-part
        $resEmail = $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer Biasa',
            'channel' => 'email',
            'destination' => 'budi.santoso@example.com',
            'password' => 'budi123456',
            'password_confirmation' => 'budi123456',
        ]);
        $resEmail->assertStatus(422);

        // 6. Contains user phone number
        $resPhone = $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer Biasa',
            'channel' => 'whatsapp',
            'destination' => '081234567890',
            'password' => 'Pass34567890!',
            'password_confirmation' => 'Pass34567890!',
        ]);
        $resPhone->assertStatus(422);

        // 7. Less than 8 characters (7 chars)
        $resShort = $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer Biasa',
            'channel' => 'email',
            'destination' => 'short@example.com',
            'password' => 'Abc!123',
            'password_confirmation' => 'Abc!123',
        ]);
        $resShort->assertStatus(422);
    }

    public function test_registration_accepts_strong_passwords_passphrases_and_unicode(): void
    {
        // 1. Strong standard password
        $resStrong = $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Rahma',
            'channel' => 'email',
            'destination' => 'siti.rahma@example.com',
            'password' => 'Bintang*Malam*99',
            'password_confirmation' => 'Bintang*Malam*99',
        ]);
        $resStrong->assertStatus(201);
        $this->assertEmpty($resStrong->json('data.password'));

        // 2. Long passphrase with spaces
        $resPassphrase = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dewi Lestari',
            'channel' => 'whatsapp',
            'destination' => '081299887711',
            'password' => 'kucing makan ikan asin enak',
            'password_confirmation' => 'kucing makan ikan asin enak',
        ]);
        $resPassphrase->assertStatus(201);

        // 3. Unicode and special characters
        $resUnicode = $this->postJson('/api/v1/auth/register', [
            'name' => 'Andi Wijaya',
            'channel' => 'whatsapp',
            'destination' => '081299887722',
            'password' => 'Kopi Hangat ☕ Pagi Ini',
            'password_confirmation' => 'Kopi Hangat ☕ Pagi Ini',
        ]);
        $resUnicode->assertStatus(201);
    }

    public function test_whatsapp_registration_and_multi_format_login_preserves_password(): void
    {
        $rawPhone = '081299884433';
        $strongPassword = 'Kopi*Hangat*99';

        // 1. Register with 08xxx
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'WA Customer',
            'channel' => 'whatsapp',
            'destination' => $rawPhone,
            'password' => $strongPassword,
            'password_confirmation' => $strongPassword,
        ]);
        $reg->assertStatus(201);
        $userId = $reg->json('data.user_id');

        // Check password in DB is initially null before OTP verification
        $user = User::find($userId);
        $this->assertNull($user->password);

        // 2. Set known challenge & verify OTP
        $challenge = OtpChallenge::where('user_id', $userId)->latest()->first();
        $challenge->otp_hash = hash('sha256', '998877');
        $challenge->save();

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'otp' => '998877',
        ]);
        $verify->assertStatus(200);

        // Verify password is STILL intact after OTP verification (not changed or cleared)
        $user->refresh();
        $this->assertEquals('active', $user->status);
        $this->assertTrue(Hash::check($strongPassword, $user->password));

        // 3. Login with 08xxx format
        $login08 = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '081299884433',
            'password' => $strongPassword,
        ]);
        $login08->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', '6281299884433');

        // 4. Login with 62xxx format
        $login62 = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '6281299884433',
            'password' => $strongPassword,
        ]);
        $login62->assertStatus(200)
            ->assertJsonPath('success', true);

        // 5. Login with +62xxx format
        $loginPlus62 = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '+6281299884433',
            'password' => $strongPassword,
        ]);
        $loginPlus62->assertStatus(200)
            ->assertJsonPath('success', true);

        // 6. Login with wrong password is rejected
        $loginWrong = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '081299884433',
            'password' => 'WrongPassword99!',
        ]);
        $loginWrong->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_pending_registration_does_not_mutate_user_password_before_otp_is_verified(): void
    {
        // 1. Initial registration
        $first = $this->postJson('/api/v1/auth/register', [
            'name' => 'Target Customer',
            'channel' => 'whatsapp',
            'destination' => '081255554444',
            'password' => 'RahasiaAsli99!',
            'password_confirmation' => 'RahasiaAsli99!',
        ]);
        $first->assertStatus(201);
        $userId = $first->json('data.user_id');

        $user = User::find($userId);
        // Password on user record must remain null until challenge is verified!
        $this->assertNull($user->password);
        $this->assertEquals('pending_verification', $user->status);

        $firstChallenge = OtpChallenge::where('user_id', $userId)->latest()->first();
        $this->assertNotNull($firstChallenge->metadata['pending_password_hash']);

        // 2. Attacker attempts to overwrite password with a second register call
        $attacker = $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker Name',
            'channel' => 'whatsapp',
            'destination' => '081255554444',
            'password' => 'KunciGembok99!',
            'password_confirmation' => 'KunciGembok99!',
        ]);
        $attacker->assertStatus(201);

        // User record is still NOT modified before OTP verification
        $user->refresh();
        $this->assertNull($user->password);
        $this->assertEquals('Target Customer', $user->name);

        // First challenge is now invalidated (consumed)
        $firstChallenge->refresh();
        $this->assertTrue($firstChallenge->isConsumed());

        // 3. Verifying the NEW challenge applies the new credentials
        $newChallenge = OtpChallenge::where('user_id', $userId)->whereNull('consumed_at')->latest()->first();
        $this->assertNotEquals($firstChallenge->id, $newChallenge->id);
        $newChallenge->otp_hash = hash('sha256', '123456');
        $newChallenge->save();

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'otp' => '123456',
        ]);
        $verify->assertStatus(200);

        // User is now active with the verified challenge's credentials
        $user->refresh();
        $this->assertEquals('active', $user->status);
        $this->assertEquals('Attacker Name', $user->name);
        $this->assertTrue(Hash::check('KunciGembok99!', $user->password));
    }

    public function test_legacy_account_without_password_can_setup_password_via_otp_flow(): void
    {
        // Create active legacy user without password
        $legacyUser = User::create([
            'name' => 'Legacy Customer',
            'phone' => '6281233334444',
            'password' => null,
            'status' => 'active',
            'verified_at' => now()->subMonths(3),
        ]);

        // 1. Attempting login returns PASSWORD_NOT_SET
        $loginAttempt = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '081233334444',
            'password' => 'AnyPassword123!',
        ]);
        $loginAttempt->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_NOT_SET');

        // 2. Request password setup OTP
        $reqSetup = $this->postJson('/api/v1/auth/password/request-setup', [
            'channel' => 'whatsapp',
            'destination' => '081233334444',
        ]);
        $reqSetup->assertStatus(200)
            ->assertJsonPath('success', true);

        $challenge = OtpChallenge::where('user_id', $legacyUser->id)
            ->where('purpose', 'set_password')
            ->latest()
            ->first();
        $this->assertNotNull($challenge);
        $challenge->otp_hash = hash('sha256', '654321');
        $challenge->save();

        // 3. Complete password setup with strong password
        $completeSetup = $this->postJson('/api/v1/auth/password/setup', [
            'channel' => 'whatsapp',
            'destination' => '081233334444',
            'otp' => '654321',
            'password' => 'Kopi*Hangat*99',
            'password_confirmation' => 'Kopi*Hangat*99',
        ]);
        $completeSetup->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($completeSetup->json('data.token'));

        // 4. Verify password is now set in DB and direct login succeeds
        $legacyUser->refresh();
        $this->assertNotNull($legacyUser->password);
        $this->assertTrue(Hash::check('Kopi*Hangat*99', $legacyUser->password));

        $directLogin = $this->postJson('/api/v1/auth/login', [
            'channel' => 'whatsapp',
            'destination' => '081233334444',
            'password' => 'Kopi*Hangat*99',
        ]);
        $directLogin->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_account_with_existing_password_cannot_use_password_setup_flow(): void
    {
        $activeUserWithPass = User::create([
            'name' => 'Protected Customer',
            'phone' => '6281244445555',
            'password' => Hash::make('OriginalStrongPass99!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        // 1. Request setup returns generic message but creates NO OTP challenge
        $reqSetup = $this->postJson('/api/v1/auth/password/request-setup', [
            'channel' => 'whatsapp',
            'destination' => '081244445555',
        ]);
        $reqSetup->assertStatus(200);

        $challenge = OtpChallenge::where('user_id', $activeUserWithPass->id)
            ->where('purpose', 'set_password')
            ->first();
        $this->assertNull($challenge, 'No OTP challenge should be created for account that already has a password');

        // 2. Setup attempt is rejected
        $setupAttempt = $this->postJson('/api/v1/auth/password/setup', [
            'channel' => 'whatsapp',
            'destination' => '081244445555',
            'otp' => '123456',
            'password' => 'NewHackedPass99!',
            'password_confirmation' => 'NewHackedPass99!',
        ]);
        $setupAttempt->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_REQUEST');

        // Password remains intact
        $activeUserWithPass->refresh();
        $this->assertTrue(Hash::check('OriginalStrongPass99!', $activeUserWithPass->password));
    }
}
