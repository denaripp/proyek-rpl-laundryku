<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the OpenAI Chat Completions API with function calling.
 *
 * No business logic here — pure transport. Any failure (network, non-200,
 * malformed payload) is converted into AiProviderException so the service
 * layer can fall back to human CS without disturbing the marketplace.
 */
class OpenAiClient implements AiClientInterface
{
    public function chat(array $messages, array $tools): array
    {
        $apiKey = config('ai.openai_api_key');

        if (empty($apiKey)) {
            if (app()->isLocal() && config('ai.mock_in_local', true)) {
                Log::info('[AI Assistant] OPENAI_API_KEY is not set; using local development simulated response.');
                return $this->simulateLocalResponse($messages, $tools);
            }

            Log::error('[AI Assistant] OpenAI API key is missing. Please configure OPENAI_API_KEY in .env.');
            throw new AiProviderException('OpenAI API key is not configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('ai.timeout', 30))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('ai.model', 'gpt-4o-mini'),
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.2,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[AI Assistant] OpenAI network request failed: ' . $e->getMessage(), ['exception' => $e]);
            throw new AiProviderException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::warning("[AI Assistant] OpenAI returned HTTP {$status}: {$body}");
            throw new AiProviderException("OpenAI returned HTTP {$status}");
        }

        $body = $response->json();
        $message = $body['choices'][0]['message'] ?? null;

        if (!is_array($message)) {
            Log::error('[AI Assistant] OpenAI returned invalid payload: ' . json_encode($body));
            throw new AiProviderException('OpenAI returned an invalid payload.');
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
            $reply = "Halo! Pertanyaan Anda: \"{$lastUserMsg}\". (Catatan: Ini adalah respon simulasi lokal. Masukkan `OPENAI_API_KEY` di file `.env` untuk mengaktifkan AI live GPT-4o-mini).";
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