<?php

namespace App\Services\WhatsApp;

interface WhatsAppSender
{
    /**
     * Send WhatsApp message.
     *
     * @param string $to Normalized recipient phone (e.g. 628123456789)
     * @param string $message Plain text message without emoji
     * @param array $metadata Contextual data
     * @return array [
     *     'success' => bool,
     *     'error' => ?string,
     *     'payload' => ?array,
     * ]
     */
    public function send(string $to, string $message, array $metadata = []): array;
}
