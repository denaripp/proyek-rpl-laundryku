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
            Log::info('FontneService::sendMessage response received', [
                'target' => $maskedPhone,
                'status_code' => $statusCode,
                'message_ids' => $messageIds,
            ]);

            if ($response->successful()) {
                return [
                    'status' => true,
                    'data' => $responseData,
                    'status_code' => $statusCode,
                ];
            }

            $errorMessage = $responseData['reason'] ?? $responseData['message'] ?? 'Unknown error from Fonnte';
            Log::error('FontneService::sendMessage failed', [
                'target' => $maskedPhone,
                'error' => $errorMessage,
                'status_code' => $statusCode,
            ]);

            throw new \Exception('Fonnte API error: ' . $errorMessage);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('FontneService connection error', [
                'target' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Koneksi ke gateway WhatsApp gagal: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function sendOtp(string $phone, string $otpCode): array
    {
        $message = "Halo! Kode verifikasi OTP LaundryKu Anda adalah: *{$otpCode}*.\n\nJangan berikan kode ini kepada siapapun demi keamanan akun Anda. Kode berlaku selama 5 menit.";
        return $this->sendMessage($phone, $message);
    }

    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }

    public static function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($cleaned);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $prefix = substr($cleaned, 0, 4);
        $suffix = substr($cleaned, -2);
        $maskedLength = $len - 6;

        if ($maskedLength <= 0) {
            return $prefix . '**';
        }

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }
}
