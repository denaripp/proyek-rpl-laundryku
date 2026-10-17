<?php

namespace App\Services\Otp;

interface OtpSender
{
    /**
     * Send OTP to destination via specified channel.
     *
     * @param string $channel 'email' | 'whatsapp'
     * @param string $destination
     * @param string $otpCode
     * @return bool
     */
    public function send(string $channel, string $destination, string $otpCode): bool;
}
