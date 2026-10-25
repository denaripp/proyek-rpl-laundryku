<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for OpenAI-compatible Chat Completions API (OpenRouter, OpenAI).
 *
 * Any failure (network, non-200, malformed payload) is converted into
 * AiProviderException so the service layer can fall back safely to CS contact
 * info without disturbing core marketplace transactions.
 */
class OpenAiClient implements AiClientInterface
{
    public function chat(array $messages, array $tools): array
    {
        $apiKey = config('ai.api_key', config('ai.openai_api_key'));

        if (empty($apiKey)) {
            if (app()->isLocal() && config('ai.mock_in_local', false)) {
                Log::info('[AI Assistant] API key is not configured; using local simulated response.');
                return $this->simulateLocalResponse($messages, $tools);
            }

            Log::error('[AI Assistant] AI API key is not configured.');
            throw new AiProviderException('AI API key is not configured.');
        }

        $baseUrl = rtrim((string) config('ai.base_url', 'https://openrouter.ai/api/v1'), '/');
        $endpoint = "{$baseUrl}/chat/completions";
        $model = config('ai.model', 'liquid/lfm-2.5-2.6b:free');

        $headers = [
            'HTTP-Referer' => config('ai.http_referer', 'http://localhost:3000'),
            'X-Title' => config('ai.x_title', 'LaundryKu'),
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->timeout((int) config('ai.timeout', 30))
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::warning('[AI Assistant] Network request failed: ' . $e->getMessage(), ['exception' => $e]);
            throw new AiProviderException('Network connection to AI provider failed: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::warning("[AI Assistant] Provider returned HTTP {$status}: {$body}");

            // If provider rejects due to tool incompatibility, retry once without tools
            $lowerBody = strtolower($body);
            if ($status === 400 && !empty($payload['tools']) && (str_contains($lowerBody, 'tool') || str_contains($lowerBody, 'function'))) {
                Log::info('[AI Assistant] Model rejected function calling tools; retrying without tools payload.');
                unset($payload['tools'], $payload['tool_choice']);
                try {
                    $retryRes = Http::withToken($apiKey)
                        ->withHeaders($headers)
                        ->timeout((int) config('ai.timeout', 30))
                        ->post($endpoint, $payload);

                    if ($retryRes->successful()) {
                        $response = $retryRes;
                    } else {
                        throw new AiProviderException("AI provider error (HTTP {$retryRes->status()}): " . $this->sanitizeErrorBody($retryRes->body()));
                    }
                } catch (\Throwable $retryErr) {
                    throw new AiProviderException("AI provider error (HTTP {$status}): " . $this->sanitizeErrorBody($body), 0, $retryErr);
                }
            } else {
                throw new AiProviderException("AI provider error (HTTP {$status}): " . $this->sanitizeErrorBody($body));
            }
        }

        $body = $response->json();
        $message = $body['choices'][0]['message'] ?? null;

        if (!is_array($message)) {
            Log::error('[AI Assistant] Provider returned invalid response structure.');
            throw new AiProviderException('AI provider returned an invalid response structure.');
        }

        $toolCalls = [];
        foreach (($message['tool_calls'] ?? []) as $call) {
            $args = json_decode($call['function']['arguments'] ?? '{}', true);
            $toolCalls[] = [
                'id' => $call['id'] ?? uniqid('call_'),
                'name' => $call['function']['name'] ?? '',
                'arguments' => is_array($args) ? $args : [],
            ];
        }

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $toolCalls,
            'usage' => [
                'prompt_tokens' => (int) ($body['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Sanitize error response body before displaying to avoid leaking sensitive internal details.
     */
    protected function sanitizeErrorBody(string $raw): string
    {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }
        return mb_substr($raw, 0, 200);
    }

    protected function simulateLocalResponse(array $messages, array $tools): array
    {
        // Extract the latest user message
        $lastUserMsg = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $lastUserMsg = (string) ($m['content'] ?? '');
                break;
            }
        }

        $lower = strtolower($lastUserMsg);

        if (str_contains($lower, 'halo') || str_contains($lower, 'hai') || str_contains($lower, 'pagi') || str_contains($lower, 'siang') || str_contains($lower, 'sore') || str_contains($lower, 'malam')) {
            $reply = "Halo! Saya Asisten AI LaundryKu (Mode Simulasi Lokal). Ada yang bisa saya bantu terkait informasi layanan, tarif laundry, atau status pesanan Anda?";
        } elseif (str_contains($lower, 'harga') || str_contains($lower, 'tarif') || str_contains($lower, 'kiloan') || str_contains($lower, 'biaya')) {
            $reply = "Tarif standar cuci komplit reguler berkisar mulai dari Rp 8.000/kg. Anda dapat melihat daftar layanan lengkap dan harga khusus pada halaman detail toko mitra terdekat di menu Marketplace.";
        } elseif (str_contains($lower, 'cari') || str_contains($lower, 'toko') || str_contains($lower, 'laundry terdekat') || str_contains($lower, 'lokasi')) {
            $reply = "Anda dapat mencari laundry terdekat melalui menu Marketplace di navigasi atas. Sistem kami otomatis mengurutkan toko berdasarkan jarak dan rating tertinggi di sekitar lokasi Anda.";
        } elseif (str_contains($lower, 'status') || str_contains($lower, 'order') || str_contains($lower, 'pesanan') || str_contains($lower, 'lacak')) {
            $reply = "Untuk memeriksa status pesanan Anda secara rinci, silakan kunjungi menu Dashboard Pelanggan atau tab Riwayat Pesanan.";
        } else {
            $reply = "Halo! Pertanyaan Anda: \"{$lastUserMsg}\". (Catatan: Ini adalah respon simulasi lokal. Masukkan `OPENROUTER_API_KEY` di file `.env` untuk mengaktifkan AI live OpenRouter).";
        }

        return [
            'content' => $reply,
            'tool_calls' => [],
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 25,
            ],
        ];
    }
}