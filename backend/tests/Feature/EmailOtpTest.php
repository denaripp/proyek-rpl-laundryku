<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * 1. Test: Sukses kirim OTP email nyata (Mail::fake).
     * Subjek jelas, kode 6 digit, delivery_status 'sent', debug_otp null.
     */
    public function test_email_otp_success_sends_email_with_6_digit_code_and_no_debug_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahmad Dahlan',
            'channel' => 'email',
            'destination' => 'ahmad@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel' => 'email',
                    'destination' => 'ahmad@example.com',
                    'otp_sent' => true,
                    'delivery_status' => 'sent',
                    'debug_otp' => null,
                ],
            ]);

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            $isCorrectRecipient = $mail->hasTo('ahmad@example.com');
            $is6DigitCode = preg_match('/^[0-9]{6}$/', $mail->otpCode) === 1;
            $has5MinutesValidity = $mail->validityMinutes === 5;
            $hasSubject = $mail->envelope()->subject === 'Kode Verifikasi OTP LaundryKu';

            // Verify rendered email content
            $rendered = $mail->render();
            $hasOtpInHtml = str_contains($rendered, $mail->otpCode);
            $hasWarningInHtml = str_contains($rendered, 'Jangan berikan kode OTP ini');
            $hasExpiryInHtml = str_contains($rendered, '5 menit');

            return $isCorrectRecipient && $is6DigitCode && $has5MinutesValidity && $hasSubject && $hasOtpInHtml && $hasWarningInHtml && $hasExpiryInHtml;
        });
    }

    /**
     * 2. Test: SMTP gagal kirim.
     * Mengembalikan error jelas, delivery_status bukan sent, UI tidak menampilkan "OTP terkirim".
     */
    public function test_email_otp_failure_when_smtp_fails(): void
    {
        // Mock Mail facade or force exception during send
        Mail::shouldReceive('to')
            ->once()
            ->with('gagal@example.com')
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Connection refused by SMTP server'));

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'User Gagal',
            'channel' => 'email',
            'destination' => 'gagal@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Gagal mengirim OTP via Email', $response->json('message'));
    }

    /**
     * 3. Test: Kredensial SMTP kosong / log mailer.
     * Mengembalikan error jelas, bukan sukses palsu / bukan simulasi.
     */
    public function test_email_otp_fails_when_smtp_credentials_empty(): void
    {
        Config::set('mail.default', 'log');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'User Kosong',
            'channel' => 'email',
            'destination' => 'kosong@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Kredensial SMTP email belum dikonfigurasi', $response->json('message'));
    }

    /**
     * 4a. Test: Verifikasi OTP salah.
     */
    public function test_email_otp_verification_fails_with_invalid_code(): void
    {
        Mail::fake();

        $registerRes = $this->postJson('/api/v1/auth/register', [
            'name' => 'Testing OTP',
            'channel' => 'email',
            'destination' => 'testotp@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $registerRes->assertStatus(201);
        $userId = $registerRes->json('data.user_id');

        // Submit wrong OTP
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'testotp@example.com',
            'otp' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                    'message' => 'Kode OTP yang Anda masukkan salah.',
                ],
            ]);
    }

    /**
     * 4b. Test: Verifikasi OTP expired.
     */
    public function test_email_otp_verification_fails_when_expired(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Expired User',
            'email' => 'expired@example.com',
            'status' => 'pending_verification',
        ]);

        $plainOtp = '123456';
        OtpChallenge::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'registration',
            'destination_hash' => hash('sha256', 'expired@example.com'),
            'otp_hash' => hash('sha256', $plainOtp),
            'metadata' => ['pending_password_hash' => Hash::make('Password123!')],
            'expires_at' => now()->subMinutes(10), // Expired
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $user->id,
            'channel' => 'email',
            'destination' => 'expired@example.com',
            'otp' => $plainOtp,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_EXPIRED',
                    'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.',
                ],
            ]);
    }

    /**
     * 4c. Test: Verifikasi OTP yang sudah terpakai (consumed).
     */
    public function test_email_otp_verification_fails_when_already_consumed(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Used User',
            'email' => 'used@example.com',
            'status' => 'pending_verification',
        ]);

        $plainOtp = '654321';
        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'registration',
            'destination_hash' => hash('sha256', 'used@example.com'),
            'otp_hash' => hash('sha256', $plainOtp),
            'metadata' => ['pending_password_hash' => Hash::make('Password123!')],
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        // First verification succeeds
        $firstRes = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $user->id,
            'channel' => 'email',
            'destination' => 'used@example.com',
            'otp' => $plainOtp,
        ]);
        $firstRes->assertStatus(200)->assertJson(['success' => true]);

        // Second verification with same code fails
        $secondRes = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $user->id,
            'channel' => 'email',
            'destination' => 'used@example.com',
            'otp' => $plainOtp,
        ]);

        $secondRes->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_ALREADY_USED',
                    'message' => 'Kode OTP sudah pernah digunakan.',
                ],
            ]);
    }

    /**
     * 5. Test: Kanal email TIDAK memanggil Fonnte (WhatsApp Gateway).
     */
    public function test_email_registration_does_not_call_fonnte_gateway(): void
    {
        Mail::fake();
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Email Only',
            'channel' => 'email',
            'destination' => 'emailonly@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        // Ensure Fonnte API was never touched
        Http::assertNothingSent();
    }

    /**
     * 6. Test: Registrasi email selesai -> Login email memakai password langsung tanpa OTP.
     */
    public function test_email_user_can_login_with_password_directly_without_otp(): void
    {
        Mail::fake();

        // Register
        $regRes = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dewi Sartika',
            'channel' => 'email',
            'destination' => 'dewi@example.com',
            'password' => 'SecretPassword999!',
            'password_confirmation' => 'SecretPassword999!',
        ]);
        $regRes->assertStatus(201);
        $userId = $regRes->json('data.user_id');

        // Grab generated OTP from database for test verification
        $challenge = OtpChallenge::where('user_id', $userId)->first();
        $this->assertNotNull($challenge);

        // Update hash to match '123456'
        $challenge->update(['otp_hash' => hash('sha256', '123456')]);

        $verifyRes = $this->postJson('/api/v1/auth/otp/verify', [
            'user_id' => $userId,
            'channel' => 'email',
            'destination' => 'dewi@example.com',
            'otp' => '123456',
        ]);
        $verifyRes->assertStatus(200);

        // Now direct Login with Email + Password
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'destination' => 'dewi@example.com',
            'channel' => 'email',
            'password' => 'SecretPassword999!',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'dewi@example.com',
                        'status' => 'active',
                    ],
                ],
            ]);

        $this->assertNotEmpty($loginRes->json('data.token'));
    }
}
