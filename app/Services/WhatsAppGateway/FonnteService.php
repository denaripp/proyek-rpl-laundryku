<?php

namespace App\Services\WhatsAppGateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected ?string $apiToken;
    protected string $baseUrl;

    public function __construct(?string $apiToken = null, ?string $baseUrl = null)
    {
        $this->apiToken = $apiToken ?? config('services.fonnte.api_token', env('FONNTE_API_TOKEN'));
        $this->baseUrl = $baseUrl ?? config('services.fonnte.base_url', env('FONNTE_BASE_URL', 'https://api.fonnte.com'));
    }

    /**
     * Send WhatsApp message via Fonnte Gateway.
     *
     * @throws \RuntimeException
     */
    public function sendMessage(string $phone, string $message): array
    {
        $maskedPhone = self::maskPhone($phone);

        if (empty($this->apiToken) || $this->apiToken === 'your_fonnte_token_here') {
            Log::warning('FonnteService: API token is not configured', ['target' => $maskedPhone]);
            throw new \RuntimeException('Gateway WhatsApp belum dikonfigurasi dengan benar.');
        }

        $normalizedPhone = self::normalizePhone($phone);
        $endpoint = rtrim($this->baseUrl, '/') . '/send';

        Log::info('FonnteService: Dispatching message', [
            'target' => $maskedPhone,
            'message_length' => strlen($message),
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
            ])
            ->timeout(15)
            ->post($endpoint, [
                'target' => $normalizedPhone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $statusCode = $response->status();
            $responseData = $response->json();

            // Fonnte returns JSON with boolean 'status' field: true on success, false on failure
            $fonnteStatus = is_array($responseData) ? ($responseData['status'] ?? null) : null;

            if ($response->successful() && $fonnteStatus === true) {
                Log::info('FonnteService: Message sent successfully', [
                    'target' => $maskedPhone,
                    'status_code' => $statusCode,
                    'process' => $responseData['process'] ?? null,
                ]);

                return [
                    'status' => true,
                    'data' => $responseData,
                    'status_code' => $statusCode,
                ];
            }

            // Handle Fonnte error response
            $reason = is_array($responseData)
                ? ($responseData['reason'] ?? $responseData['detail'] ?? $responseData['message'] ?? 'Gateway menolak pengiriman.')
                : 'Respons gateway tidak valid.';

            Log::error('FonnteService: Gateway returned failure', [
                'target' => $maskedPhone,
                'status_code' => $statusCode,
                'reason' => $reason,
            ]);

            throw new \RuntimeException("Pengiriman WhatsApp gagal: {$reason}");
        } catch (ConnectionException $e) {
            Log::error('FonnteService: Connection timeout or network error', [
                'target' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Koneksi ke gateway WhatsApp timeout. Silakan coba beberapa saat lagi.');
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('FonnteService: Unexpected error during dispatch', [
                'target' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Terjadi kendala saat mengirim pesan WhatsApp: ' . $e->getMessage());
        }
    }

    /**
     * Send formatted OTP code via WhatsApp.
     */
    public function sendOtp(string $phone, string $otpCode, int $validityMinutes = 5): array
    {
        $message = "Halo! Kode verifikasi OTP LaundryKu Anda adalah: *{$otpCode}*.\n\nJangan berikan kode ini kepada siapapun demi keamanan akun Anda. Kode berlaku selama {$validityMinutes} menit.";
        return $this->sendMessage($phone, $message);
    }

    /**
     * Normalize destination phone number to Fonnte expected format (628xxx).
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string) $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Mask phone number for safe logging (e.g. 6281****7890).
     */
    public static function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string) $phone);
        $len = strlen($cleaned);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $prefix = substr($cleaned, 0, 4);
        $suffix = substr($cleaned, -2);
        $maskedLength = max(0, $len - 6);

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }
}
