<?php

namespace App\Services\WhatsAppGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FontneService
{
    private ?string $apiToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiToken = config('services.fonnte.api_token');
        $this->baseUrl = config('services.fonnte.base_url', 'https://api.fonnte.com');
    }

    /**
     * Send WhatsApp message via Fonnte
     * Official docs: POST https://api.fonnte.com/send
     *
     * @param string $phone Format: 628xxx (without +)
     * @param string $message Message content
     * @return array Response from Fonnte API
     * @throws \Exception
     */
    public function sendMessage(string $phone, string $message): array
    {
        $maskedPhone = self::maskPhone($phone);
        Log::info('FontneService::sendMessage initiated', [
            'target' => $maskedPhone,
            'message_length' => strlen($message),
        ]);

        if (empty($this->apiToken) || $this->apiToken === 'your_fonnte_token_here') {
            $error = 'Fonnte API token not configured';
            Log::warning($error);
            throw new \Exception($error);
        }

        $normalizedPhone = $this->normalizePhone($phone);

        try {
            $endpoint = rtrim($this->baseUrl, '/') . '/send';

            $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
            ])
            ->timeout(10)
            ->post($endpoint, [
                'target' => $normalizedPhone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $statusCode = $response->status();
            $responseData = $response->json();

            $messageIds = is_array($responseData) ? ($responseData['id'] ?? $responseData['message_id'] ?? null) : null;
            $processStatus = is_array($responseData) ? ($responseData['process'] ?? null) : null;
            $providerStatus = is_array($responseData) ? ($responseData['status'] ?? null) : null;

            Log::info('Fonnte API response received', [
                'status_code' => $statusCode,
                'provider_status' => $providerStatus,
                'process' => $processStatus,
                'message_id' => is_array($messageIds) ? implode(',', $messageIds) : $messageIds,
            ]);

            if ($response->failed()) {
                $errorMsg = is_array($responseData) ? ($responseData['reason'] ?? $responseData['message'] ?? $response->body()) : $response->body();
                throw new \Exception('Fonnte API HTTP error (' . $statusCode . '): ' . $errorMsg);
            }

            // Check if response contains status: false despite HTTP 200
            if (is_array($responseData) && array_key_exists('status', $responseData) && $responseData['status'] === false) {
                $reason = $responseData['reason'] ?? $responseData['message'] ?? 'Provider rejected message';
                throw new \Exception('Fonnte delivery error: ' . $reason);
            }

            return is_array($responseData) ? $responseData : ['status' => true, 'raw' => $response->body()];

        } catch (\Exception $e) {
            Log::error('Fonnte API call failed', [
                'target' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send OTP message
     */
    public function sendOtp(string $phone, string $code, string $appName = 'LaundryKu'): array
    {
        $message = "Kode OTP $appName Anda: *$code*\n\nKode ini berlaku 5 menit. Jangan bagikan ke siapapun.";
        return $this->sendMessage($phone, $message);
    }

    /**
     * Normalize phone: 08xxx or +62xxx → 628xxx
     */
    public function normalizePhone(string $phone): string
    {
        $cleaned = str_replace([' ', '-', '+'], '', $phone);

        if (str_starts_with($cleaned, '0')) {
            return '62' . substr($cleaned, 1);
        }

        if (!str_starts_with($cleaned, '62')) {
            throw new \InvalidArgumentException('Format nomor telepon tidak valid. Gunakan format 08xxx atau 628xxx.');
        }

        return $cleaned;
    }

    /**
     * Helper to mask phone number for safe logging.
     */
    public static function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }

    /**
     * Verify webhook signature from Fonnte
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $webhookToken = config('services.fonnte.webhook_token');
        $computed = hash_hmac('sha256', json_encode($payload), (string) $webhookToken);
        return hash_equals($computed, $signature);
    }
}
