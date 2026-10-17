<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSender
{
    /**
     * Log dummy OTP dispatch.
     *
     * @param string $channel
     * @param string $destination
     * @param string $otpCode
     * @return bool
     */
    public function send(string $channel, string $destination, string $otpCode): bool
    {
        $maskedDestination = $channel === 'whatsapp' ? substr($destination, 0, 4) . '****' : (substr($destination, 0, 2) . '***@' . (explode('@', $destination)[1] ?? 'domain'));
        Log::info(sprintf('[DUMMY OTP DISPATCH][%s] Target: %s (expires in 5 minutes)', strtoupper($channel), $maskedDestination));
        return true;
    }
}
