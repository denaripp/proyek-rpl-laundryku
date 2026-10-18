<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSender
{
    public function send(string $channel, string $destination, string $otpCode): bool
    {
        Log::info("[OTP SIMULATION] Code: {$otpCode} | Channel: {$channel} | Destination: {$destination}");
        return true;
    }
}
