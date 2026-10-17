<?php

namespace App\Services\Otp;

use App\Mail\OtpMail;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FonnteOtpSender implements OtpSender
{
    public function __construct(
        protected ?FontneService $fonnteService = null
    ) {
        $this->fonnteService = $fonnteService ?? new FontneService();
    }

    /**
     * Send OTP via WhatsApp (Fonnte) or Email (SMTP).
     *
     * @param string $channel
     * @param string $destination
     * @param string $otpCode
     * @return bool
     */
    public function send(string $channel, string $destination, string $otpCode): bool
    {
        if ($channel === 'whatsapp') {
            try {
                $this->fonnteService->sendOtp($destination, $otpCode);
                return true;
            } catch (\Throwable $e) {
                $maskedPhone = FontneService::maskPhone($destination);
                Log::error("FonnteOtpSender failed to send WhatsApp OTP: " . $e->getMessage(), [
                    'destination' => $maskedPhone,
                    'error' => $e->getMessage(),
                ]);

                // If in local/testing environment or token not set, don't break flow
                if (app()->environment('local', 'testing') || empty(config('services.fonnte.api_token')) || config('services.fonnte.api_token') === 'your_fonnte_token_here') {
                    Log::info(sprintf('[FONNTE FALLBACK LOG][%s] Target: %s', strtoupper($channel), $maskedPhone));
                    return true;
                }

                throw $e;
            }
        }

        if ($channel === 'email') {
            if (!OtpService::isSmtpConfigured()) {
                throw new \Exception('Kredensial SMTP email belum dikonfigurasi. Pengiriman OTP email tidak dapat diproses.');
            }

            Mail::to($destination)->send(new OtpMail($otpCode, OtpService::VALIDITY_MINUTES));
            return true;
        }

        return true;
    }
}
