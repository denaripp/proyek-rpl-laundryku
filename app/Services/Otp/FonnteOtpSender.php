<?php

namespace App\Services\Otp;

use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Support\Facades\Log;

class FonnteOtpSender implements OtpSender
{
    public function __construct(
        protected ?FontneService $fontneService = null
    ) {
        $this->fontneService = $fontneService ?? app(FontneService::class);
    }

    public function send(string $channel, string $destination, string $otpCode): bool
    {
        if ($channel !== 'whatsapp') {
            Log::warning("FonnteOtpSender only supports 'whatsapp' channel, received: {$channel}");
            return false;
        }

        try {
            $this->fontneService->sendOtp($destination, $otpCode);
            return true;
        } catch (\Throwable $e) {
            Log::error("FonnteOtpSender failed to send OTP: " . $e->getMessage());
            return false;
        }
    }
}
