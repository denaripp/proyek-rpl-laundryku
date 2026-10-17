<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Services\Otp\OtpSender;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Default validity duration in minutes.
     */
    public const VALIDITY_MINUTES = 5;

    /**
     * Maximum allowed verification attempts per OTP challenge.
     */
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected ?OtpSender $otpSender = null
    ) {
        $this->otpSender = $otpSender ?? app(OtpSender::class);
    }

    /**
     * Check if real SMTP email sending is properly configured.
     */
    public static function isSmtpConfigured(): bool
    {
        $mailer = config('mail.default');

        // Disallow log, null, or empty mailer
        if (empty($mailer) || in_array(strtolower($mailer), ['log', 'null'])) {
            return false;
        }

        // In tests, if MailFake is active, allow sending via fake
        if (Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake) {
            return true;
        }

        // If in testing environment and mailer is array (default in phpunit.xml without fake)
        if (app()->environment('testing') && strtolower($mailer) === 'array') {
            return true;
        }

        // If mailer is smtp, check credentials
        if (strtolower($mailer) === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');
            $fromAddress = config('mail.from.address');

            if (empty($host) || empty($username) || empty($password) || empty($fromAddress)) {
                return false;
            }

            if ($username === 'null' || $password === 'null' || $fromAddress === 'hello@example.com') {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Generate and send OTP via static helper (Fase 15B / 15C).
     *
     * @param string $phone
     * @param string $channel
     * @return OtpChallenge
     * @throws \Exception
     */
    public static function generateAndSendOtp($phone, $channel = 'whatsapp')
    {
        $maskedPhone = $channel === 'whatsapp'
            ? FontneService::maskPhone($phone)
            : (substr($phone, 0, 2) . '***@' . (explode('@', $phone)[1] ?? 'domain'));

        Log::info('OtpService::generateAndSendOtp initiated', [
            'target' => $maskedPhone,
            'channel' => $channel,
        ]);

        // Validate phone if whatsapp
        if ($channel === 'whatsapp' && !self::isValidPhone($phone)) {
            $error = 'Format nomor telepon tidak valid. Gunakan format 08xxx atau 628xxx.';
            Log::warning('OtpService invalid phone format', ['target' => $maskedPhone]);
            throw new \InvalidArgumentException($error);
        }

        // Normalize
        $normalizedDestination = $channel === 'whatsapp' ? self::normalizePhone($phone) : strtolower(trim($phone));

        // Find or create pending user
        $user = User::where($channel === 'whatsapp' ? 'phone' : 'email', $normalizedDestination)->first();
        if (!$user) {
            $user = DB::transaction(function () use ($normalizedDestination, $channel) {
                $userData = [
                    'name' => $channel === 'whatsapp' ? 'User ' . substr($normalizedDestination, -4) : explode('@', $normalizedDestination)[0],
                    'status' => 'pending_verification',
                ];
                if ($channel === 'whatsapp') {
                    $userData['phone'] = $normalizedDestination;
                } else {
                    $userData['email'] = $normalizedDestination;
                }
                $createdUser = User::create($userData);
                $customerRole = Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer']);
                UserRole::create([
                    'user_id' => $createdUser->id,
                    'role_id' => $customerRole->id,
                    'status' => 'active',
                ]);
                UserProfile::create(['user_id' => $createdUser->id]);
                return $createdUser;
            });
        }

        // Invalidate previous unconsumed challenges
        OtpChallenge::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // Generate 6-digit numeric OTP code
        $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save to DB
        try {
            $otpChallenge = OtpChallenge::create([
                'user_id' => $user->id,
                'channel' => $channel,
                'destination_hash' => hash('sha256', strtolower(trim($normalizedDestination))),
                'otp_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(self::VALIDITY_MINUTES),
                'attempts' => 0,
                'consumed_at' => null,
            ]);
            Log::info('OTP challenge recorded in database', ['otp_id' => $otpChallenge->id]);
        } catch (\Exception $e) {
            Log::error('Failed to record OTP in database', ['error' => $e->getMessage()]);
            throw $e;
        }

        // Send via channel
        try {
            if ($channel === 'whatsapp') {
                $token = config('services.fonnte.api_token');
                $hasConfiguredToken = !empty($token) && $token !== 'your_fonnte_token_here';
                if ($hasConfiguredToken) {
                    $fonnte = new FontneService();
                    $result = $fonnte->sendOtp($normalizedDestination, $code);
                    Log::info('Fonnte sendOtp success', ['status' => $result['status'] ?? true]);
                } else {
                    Log::info('Fonnte running in simulated/dummy mode (token not configured)');
                }
            } elseif ($channel === 'email') {
                if (!self::isSmtpConfigured()) {
                    throw new \Exception('Kredensial SMTP email belum dikonfigurasi. Pengiriman OTP email tidak dapat diproses.');
                }
                Mail::to($normalizedDestination)->send(new OtpMail($code, self::VALIDITY_MINUTES));
                Log::info('Email OTP sent successfully', ['target' => $maskedPhone]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send OTP via channel', [
                'channel' => $channel,
                'target' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Gagal mengirim OTP: ' . $e->getMessage());
        }

        return $otpChallenge;
    }

    /**
     * Normalize phone: 08xxx or +62xxx → 628xxx
     */
    public static function normalizePhone($phone)
    {
        $phone = preg_replace('/[^\d]/', '', (string) $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            return '62' . $phone;
        }

        return $phone;
    }

    /**
     * Validate Indonesian phone format.
     */
    public static function isValidPhone($phone)
    {
        $normalized = self::normalizePhone($phone);
        return (bool) preg_match('/^62[0-9]{8,13}$/', $normalized);
    }

    /**
     * Generate a new OTP challenge for a user and dispatch via OtpSender / Fonnte / SMTP.
     *
     * @param User $user
     * @param string $channel 'email' or 'whatsapp'
     * @param string $destination
     * @param string $purpose 'registration' | 'set_password'
     * @param array $metadata Pending registration or purpose-specific metadata
     * @return array{challenge: OtpChallenge, otp_sent: bool, delivery_status: string, debug_otp: ?string}
     */
    public function generateOtp(User $user, string $channel, string $destination, string $purpose = 'registration', array $metadata = []): array
    {
        $normalizedDestination = $this->normalizeDestination($channel, $destination);
        $maskedDestination = $channel === 'whatsapp'
            ? FontneService::maskPhone($normalizedDestination)
            : (substr($normalizedDestination, 0, 2) . '***@' . (explode('@', $normalizedDestination)[1] ?? 'domain'));

        // Invalidate previous unconsumed challenges for this user
        OtpChallenge::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // Generate 6-digit numeric OTP code
        $plainOtp = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'purpose' => $purpose,
            'destination_hash' => hash('sha256', strtolower(trim($normalizedDestination))),
            'otp_hash' => hash('sha256', $plainOtp),
            'metadata' => !empty($metadata) ? $metadata : null,
            'expires_at' => now()->addMinutes(self::VALIDITY_MINUTES),
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        Log::info('OTP challenge generated for user', [
            'user_id' => $user->id,
            'target' => $maskedDestination,
            'channel' => $channel,
            'purpose' => $purpose,
            'otp_id' => $challenge->id,
        ]);

        $otpSent = false;
        $deliveryStatus = 'simulated';

        // Dispatch via WhatsApp or Email
        if ($channel === 'whatsapp') {
            $token = config('services.fonnte.api_token');
            $hasConfiguredToken = !empty($token) && $token !== 'your_fonnte_token_here';

            if ($hasConfiguredToken) {
                try {
                    $fonnte = new FontneService();
                    $result = $fonnte->sendOtp($normalizedDestination, $plainOtp);
                    $otpSent = true;
                    $deliveryStatus = 'sent';
                    Log::info('Fonnte sendOtp success', ['status' => $result['status'] ?? true]);
                } catch (\Throwable $e) {
                    Log::error('Fonnte sendOtp failed: ' . $e->getMessage(), [
                        'target' => $maskedDestination,
                    ]);
                    throw new \Exception('Gagal mengirim OTP via WhatsApp: ' . $e->getMessage());
                }
            } else {
                $this->otpSender?->send($channel, $normalizedDestination, $plainOtp);
                $otpSent = false;
                $deliveryStatus = 'simulated';
            }
        } else {
            // Email channel
            if (!self::isSmtpConfigured()) {
                Log::error('SMTP credentials missing or mailer is not configured', [
                    'target' => $maskedDestination,
                ]);
                throw new \Exception('Kredensial SMTP email belum dikonfigurasi. Pengiriman OTP email tidak dapat diproses.');
            }

            try {
                Mail::to($normalizedDestination)->send(new OtpMail($plainOtp, self::VALIDITY_MINUTES));
                $otpSent = true;
                $deliveryStatus = 'sent';
                Log::info('Email OTP sent successfully', [
                    'target' => $maskedDestination,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send OTP via Email: ' . $e->getMessage(), [
                    'target' => $maskedDestination,
                ]);
                throw new \Exception('Gagal mengirim OTP via Email: ' . $e->getMessage());
            }
        }

        // Also record multi-channel notification entry (FR-NOTIF)
        try {
            app(\App\Services\Notification\NotificationService::class)->notifyOtp($user, $channel, $normalizedDestination, $plainOtp);
        } catch (\Throwable) {
            // Ignore failure to ensure auth flow never breaks
        }

        // debug_otp only provided in simulated mode on local/testing environment
        $debugOtp = ($deliveryStatus === 'simulated' && app()->environment('local', 'testing')) ? $plainOtp : null;

        return [
            'challenge' => $challenge,
            'otp_sent' => $otpSent,
            'delivery_status' => $deliveryStatus,
            'debug_otp' => $debugOtp,
        ];
    }

    /**
     * Verify an OTP challenge.
     *
     * @param User $user
     * @param string $otp
     * @param string|null $channel
     * @param string|null $purpose
     * @return array{success: bool, code?: string, message: string, user?: User, challenge?: OtpChallenge}
     */
    public function verifyOtp(User $user, string $otp, ?string $channel = null, ?string $purpose = null): array
    {
        // Account status guard: suspended or banned accounts must be rejected
        if (in_array($user->status, ['suspended', 'banned'])) {
            return [
                'success' => false,
                'code' => 'ACCOUNT_' . strtoupper($user->status),
                'message' => 'Akun Anda sedang di-' . $user->status . '. Silakan hubungi dukungan pelanggan.',
            ];
        }

        $query = OtpChallenge::where('user_id', $user->id)
            ->orderBy('id', 'desc');

        if ($channel) {
            $query->where('channel', $channel);
        }

        if ($purpose) {
            $query->where('purpose', $purpose);
        }

        /** @var OtpChallenge|null $challenge */
        $challenge = $query->first();

        if (!$challenge) {
            return [
                'success' => false,
                'code' => 'OTP_NOT_FOUND',
                'message' => 'Kode OTP tidak ditemukan.',
            ];
        }

        if ($challenge->isConsumed()) {
            return [
                'success' => false,
                'code' => 'OTP_ALREADY_USED',
                'message' => 'Kode OTP sudah pernah digunakan.',
            ];
        }

        if ($challenge->isExpired()) {
            return [
                'success' => false,
                'code' => 'OTP_EXPIRED',
                'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.',
            ];
        }

        if ($challenge->attempts >= self::MAX_ATTEMPTS) {
            return [
                'success' => false,
                'code' => 'OTP_MAX_ATTEMPTS',
                'message' => 'Batas percobaan OTP telah terlampaui. Silakan minta kode baru.',
            ];
        }

        $challenge->increment('attempts');

        $inputHash = hash('sha256', trim($otp));
        if (!hash_equals($challenge->otp_hash, $inputHash)) {
            return [
                'success' => false,
                'code' => 'OTP_INVALID',
                'message' => 'Kode OTP yang Anda masukkan salah.',
            ];
        }

        // Mark OTP as consumed
        $challenge->consumed_at = now();
        $challenge->save();

        // Apply pending credentials from verified challenge metadata
        if (!empty($challenge->metadata['pending_password_hash'])) {
            $user->password = $challenge->metadata['pending_password_hash'];
        }
        if (!empty($challenge->metadata['pending_name'])) {
            $user->name = $challenge->metadata['pending_name'];
        }

        // If user is pending verification, activate user
        if ($user->status === 'pending_verification') {
            $user->status = 'active';
            $user->verified_at = now();
        }

        $user->save();

        return [
            'success' => true,
            'message' => 'Verifikasi OTP berhasil.',
            'user' => $user->fresh(['roles', 'profile']),
            'challenge' => $challenge,
        ];
    }

    /**
     * Normalize destination based on channel (e.g. Indonesian phone numbers).
     */
    public function normalizeDestination(string $channel, string $destination): string
    {
        $destination = trim($destination);

        if ($channel === 'whatsapp') {
            return self::normalizePhone($destination);
        }

        return strtolower($destination);
    }
}
