<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const VALIDITY_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected ?OtpSender $otpSender = null
    ) {
        $this->otpSender = $otpSender ?? app(LogOtpSender::class);
    }

    public static function isSmtpConfigured(): bool
    {
        $mailer = config('mail.default');

        if (empty($mailer) || in_array(strtolower($mailer), ['log', 'null'])) {
            return false;
        }

        if (Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake) {
            return true;
        }

        if (app()->environment('testing') && strtolower($mailer) === 'array') {
            return true;
        }

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

    public static function normalizePhone(string $phone): string
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

    public static function isValidPhone(string $phone): bool
    {
        $normalized = self::normalizePhone($phone);
        return (bool) preg_match('/^62[0-9]{8,13}$/', $normalized);
    }

    public function normalizeDestination(string $channel, string $destination): string
    {
        $destination = trim($destination);

        if ($channel === 'whatsapp') {
            return self::normalizePhone($destination);
        }

        return strtolower($destination);
    }

    /**
     * Generate a new OTP challenge and dispatch via selected channel.
     *
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

        $otpSent = false;
        $deliveryStatus = 'simulated';

        if ($channel === 'whatsapp') {
            $token = config('services.fonnte.api_token');
            $hasConfiguredToken = !empty($token) && $token !== 'your_fonnte_token_here';

            if ($hasConfiguredToken) {
                try {
                    $fonnte = new FontneService();
                    $fonnte->sendOtp($normalizedDestination, $plainOtp);
                    $otpSent = true;
                    $deliveryStatus = 'sent';
                    Log::info('Fonnte sendOtp success', ['target' => $maskedDestination]);
                } catch (\Throwable $e) {
                    Log::error('Fonnte sendOtp failed: ' . $e->getMessage(), ['target' => $maskedDestination]);
                    // Fall back to simulation on dev/test
                    if (!app()->environment('production')) {
                        $otpSent = false;
                        $deliveryStatus = 'simulated';
                    } else {
                        throw new \Exception('Gagal mengirim OTP via WhatsApp: ' . $e->getMessage());
                    }
                }
            } else {
                $this->otpSender?->send($channel, $normalizedDestination, $plainOtp);
                $otpSent = false;
                $deliveryStatus = 'simulated';
            }
        } else {
            // Email channel
            if (self::isSmtpConfigured()) {
                try {
                    Mail::to($normalizedDestination)->send(new OtpMail($plainOtp, self::VALIDITY_MINUTES));
                    $otpSent = true;
                    $deliveryStatus = 'sent';
                    Log::info('Email OTP sent successfully', ['target' => $maskedDestination]);
                } catch (\Throwable $e) {
                    Log::error('Failed to send OTP via Email: ' . $e->getMessage(), ['target' => $maskedDestination]);
                    if (!app()->environment('production')) {
                        $otpSent = false;
                        $deliveryStatus = 'simulated';
                    } else {
                        throw new \Exception('Gagal mengirim OTP via Email: ' . $e->getMessage());
                    }
                }
            } else {
                // SMTP not configured - simulate in local / testing
                Log::info('SMTP not configured; simulating OTP delivery in local/test', ['target' => $maskedDestination]);
                $otpSent = false;
                $deliveryStatus = 'simulated';
            }
        }

        // debug_otp provided in simulated mode on local/testing environment
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
     * @return array{success: bool, code?: string, message: string, user?: User, challenge?: OtpChallenge}
     */
    public function verifyOtp(User $user, string $otp, ?string $channel = null, ?string $purpose = null): array
    {
        $query = OtpChallenge::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

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

        // Activate user
        $user->is_active = true;
        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }
        $user->save();

        return [
            'success' => true,
            'message' => 'Verifikasi OTP berhasil.',
            'user' => $user->fresh(['roles']),
            'challenge' => $challenge,
        ];
    }
}
