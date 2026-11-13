<?php

namespace App\Services\Ai;

use App\Models\Setting;
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
            if ((app()->isLocal() || app()->runningUnitTests()) && config('ai.mock_in_local', false)) {
                Log::info('[AI Assistant] API key is not configured; using local simulated response.');
                return $this->simulateLocalResponse($messages, $tools);
            }

            Log::error('[AI Assistant] AI API key is not configured.');
            throw new AiProviderException('AI API key is not configured.');
        }

        $baseUrl = rtrim((string) config('ai.base_url', 'https://openrouter.ai/api/v1'), '/');
        $endpoint = "{$baseUrl}/chat/completions";
        $model = Setting::get('AI_MODEL') ?: config('ai.model', 'liquid/lfm-2.5-2.6b:free');
        $rawTemp = Setting::get('AI_TEMPERATURE');
        $temperature = is_numeric($rawTemp) ? (float) $rawTemp : (float) config('ai.temperature', 0.2);

        $headers = [
            'HTTP-Referer' => config('ai.http_referer', 'http://localhost:3000'),
            'X-Title' => config('ai.x_title', 'LaundryKu'),
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
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
            'model' => is_string($body['model'] ?? null) ? trim($body['model']) : $model,
            'usage' => [
                'prompt_tokens' => (int) ($body['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Test connection to a specific model via OpenRouter/OpenAI API.
     *
     * @return array{success: bool, latency_ms: int, message: string, error?: string, model: string}
     */
    public function testConnection(string $model): array
    {
        $apiKey = config('ai.api_key', config('ai.openai_api_key'));

        if (empty($apiKey)) {
            if ((app()->isLocal() || app()->runningUnitTests()) && config('ai.mock_in_local', false)) {
                return [
                    'success' => true,
                    'latency_ms' => 45,
                    'message' => "Simulasi koneksi model {$model} berhasil (mock mode aktif).",
                    'model' => $model,
                ];
            }

            return [
                'success' => false,
                'latency_ms' => 0,
                'message' => 'API Key AI belum dikonfigurasi di server (.env).',
                'error' => 'AI_API_KEY_NOT_CONFIGURED',
                'model' => $model,
            ];
        }

        $baseUrl = rtrim((string) config('ai.base_url', 'https://openrouter.ai/api/v1'), '/');
        $endpoint = "{$baseUrl}/chat/completions";

        $headers = [
            'HTTP-Referer' => config('ai.http_referer', 'http://localhost:3000'),
            'X-Title' => config('ai.x_title', 'LaundryKu'),
        ];

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Ping test. Reply with "PONG" only.'],
            ],
            'max_tokens' => 10,
            'temperature' => 0,
        ];

        $startTime = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->timeout((int) config('ai.timeout', 15))
                ->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $body = $response->json();
                $content = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
                $resolved = is_string($body['model'] ?? null) ? trim($body['model']) : $model;

                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'message' => "Koneksi ke model {$model} berhasil (respon dalam {$latencyMs}ms).",
                    'model' => $model,
                    'resolved_model' => $resolved,
                    'reply' => $content ?: 'PONG',
                ];
            }

            $errMsg = $this->sanitizeErrorBody($response->body());
            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => "Koneksi gagal (HTTP {$response->status()}): {$errMsg}",
                'error' => $errMsg,
                'model' => $model,
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'Gagal menghubungi server AI: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'model' => $model,
            ];
        }
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
        // 1. Check if the last message is a tool output (Round 2: format answer from tool result)
        $lastToolMsg = null;
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'tool') {
                $lastToolMsg = $m;
                break;
            }
        }

        if ($lastToolMsg !== null) {
            $toolData = json_decode((string) ($lastToolMsg['content'] ?? '{}'), true);
            $reply = $this->formatToolResultToAnswer($toolData);

            return [
                'content' => $reply,
                'tool_calls' => [],
                'usage' => [
                    'prompt_tokens' => 25,
                    'completion_tokens' => 40,
                ],
            ];
        }

        // 2. Round 1: inspect the user message and determine which tool from $tools to invoke
        $lastUserMsg = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $lastUserMsg = (string) ($m['content'] ?? '');
                break;
            }
        }

        $lower = strtolower(trim($lastUserMsg));
        $toolNames = array_map(fn ($t) => $t['function']['name'] ?? '', $tools);

        // Extract order number if present (e.g. ORD-...)
        $orderNumber = null;
        if (preg_match('/\b(ord-[a-z0-9\-]+)\b/i', $lastUserMsg, $match)) {
            $orderNumber = strtoupper($match[1]);
        }

        // Bot identity queries: answer directly without tool
        if (str_contains($lower, 'siapa kamu') || str_contains($lower, 'siapa anda') || str_contains($lower, 'kamu siapa') || str_contains($lower, 'anda siapa') || str_contains($lower, 'siapakah anda') || str_contains($lower, 'siapakah kamu')) {
            return [
                'content' => 'Saya LANDY, asisten AI resmi LaundryKu. Saya siap membantu Anda memeriksa informasi pesanan, operasional, layanan, dan data akun Anda.',
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 25],
            ];
        }

        // Match tools from available $tools:
        $matchedTool = null;
        $matchedArgs = [];

        // Identity / Profile
        if (str_contains($lower, 'siapa saya') || str_contains($lower, 'profil') || str_contains($lower, 'nama saya') || str_contains($lower, 'role saya') || str_contains($lower, 'akun saya') || str_contains($lower, 'siapakah saya')) {
            if (in_array('get_user_profile', $toolNames, true)) {
                $matchedTool = 'get_user_profile';
            }
        }

        // Specific order status / detail
        if ($orderNumber !== null && $matchedTool === null) {
            if (in_array('customer_get_order_detail', $toolNames, true)) {
                $matchedTool = 'customer_get_order_detail';
                $matchedArgs = ['order_number' => $orderNumber];
            } elseif (in_array('store_get_order_detail', $toolNames, true)) {
                $matchedTool = 'store_get_order_detail';
                $matchedArgs = ['order_number' => $orderNumber];
            } elseif (in_array('get_customer_order_status', $toolNames, true)) {
                $matchedTool = 'get_customer_order_status';
                $matchedArgs = ['order_id' => 1];
            }
        }

        // Payments
        if ($matchedTool === null && (str_contains($lower, 'bayar') || str_contains($lower, 'pembayaran') || str_contains($lower, 'lunas') || str_contains($lower, 'tagihan'))) {
            if (in_array('customer_get_payment_status', $toolNames, true)) {
                $matchedTool = 'customer_get_payment_status';
                if ($orderNumber) $matchedArgs = ['order_number' => $orderNumber];
            }
        }

        // Reviews
        if ($matchedTool === null && (str_contains($lower, 'ulasan') || str_contains($lower, 'rating') || str_contains($lower, 'review'))) {
            if (in_array('store_get_reviews', $toolNames, true)) {
                $matchedTool = 'store_get_reviews';
            } elseif (in_array('customer_get_reviews', $toolNames, true)) {
                $matchedTool = 'customer_get_reviews';
            }
        }

        // Complaints / Disputes
        if ($matchedTool === null && (str_contains($lower, 'komplain') || str_contains($lower, 'dispute') || str_contains($lower, 'kendala cucian') || str_contains($lower, 'keluhan'))) {
            if (in_array('platform_get_complaints', $toolNames, true)) {
                $matchedTool = 'platform_get_complaints';
            } elseif (in_array('store_get_complaints', $toolNames, true)) {
                $matchedTool = 'store_get_complaints';
            } elseif (in_array('customer_get_complaints', $toolNames, true)) {
                $matchedTool = 'customer_get_complaints';
            }
        }

        // Store summary / condition / omzet / performa
        if ($matchedTool === null && (str_contains($lower, 'ringkasan toko') || str_contains($lower, 'kondisi toko') || str_contains($lower, 'omzet') || str_contains($lower, 'rekap') || str_contains($lower, 'pemasukan') || str_contains($lower, 'performa toko') || str_contains($lower, 'dashboard'))) {
            if (in_array('store_get_summary', $toolNames, true)) {
                $matchedTool = 'store_get_summary';
            }
        }

        // Store Couriers
        if ($matchedTool === null && (str_contains($lower, 'kurir') && (str_contains($lower, 'toko') || str_contains($lower, 'daftar') || str_contains($lower, 'siapa')))) {
            if (in_array('store_get_couriers', $toolNames, true)) {
                $matchedTool = 'store_get_couriers';
            }
        }

        // Services & Prices
        if ($matchedTool === null && (str_contains($lower, 'layanan') || str_contains($lower, 'tarif') || str_contains($lower, 'harga') || str_contains($lower, 'kiloan'))) {
            if (in_array('store_get_services', $toolNames, true)) {
                $matchedTool = 'store_get_services';
            } elseif (in_array('get_services_and_prices', $toolNames, true)) {
                $matchedTool = 'get_services_and_prices';
            }
        }

        // Courier tasks
        if ($matchedTool === null && (str_contains($lower, 'tugas') || str_contains($lower, 'jemputan') || str_contains($lower, 'antaran') || str_contains($lower, 'orderan kurir'))) {
            if (str_contains($lower, 'statistik') || str_contains($lower, 'berapa')) {
                if (in_array('courier_get_task_stats', $toolNames, true)) {
                    $matchedTool = 'courier_get_task_stats';
                }
            } else {
                if (in_array('courier_get_tasks', $toolNames, true)) {
                    $matchedTool = 'courier_get_tasks';
                }
            }
        }

        // Courier profile
        if ($matchedTool === null && (str_contains($lower, 'kendaraan') || str_contains($lower, 'plat') || str_contains($lower, 'ketersediaan') || str_contains($lower, 'siap tugas'))) {
            if (in_array('courier_get_profile', $toolNames, true)) {
                $matchedTool = 'courier_get_profile';
            }
        }

        // COD rules
        if ($matchedTool === null && str_contains($lower, 'cod')) {
            if (in_array('courier_get_cod_rules', $toolNames, true)) {
                $matchedTool = 'courier_get_cod_rules';
            }
        }

        // Platform overview / stats
        if ($matchedTool === null && (str_contains($lower, 'platform') || str_contains($lower, 'gmv') || str_contains($lower, 'ekosistem'))) {
            if (in_array('platform_get_overview', $toolNames, true)) {
                $matchedTool = 'platform_get_overview';
            }
        }

        // Platform search users or account linkage
        if ($matchedTool === null && (str_contains($lower, 'cari user') || str_contains($lower, 'cari pengguna') || str_contains($lower, 'cari akun') || str_contains($lower, 'terhubung ke toko') || str_contains($lower, 'keterkaitan akun') || str_contains($lower, 'toko mana'))) {
            if (in_array('platform_search_users', $toolNames, true)) {
                $matchedTool = 'platform_search_users';
                $cleanQuery = $lastUserMsg;
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $lastUserMsg, $em)) {
                    $cleanQuery = $em[0];
                } elseif (preg_match('/(?:bernama|nama)\s+([a-zA-Z0-9\s._-]+?)(?:\s+terhubung|\s+ke|\?|$)/i', $lastUserMsg, $nm)) {
                    $cleanQuery = trim($nm[1]);
                } elseif (preg_match('/(?:pengguna|user|akun)\s+([a-zA-Z0-9\s._-]+?)(?:\s+terhubung|\s+ke|\?|$)/i', $lastUserMsg, $nm)) {
                    $cleanQuery = trim($nm[1]);
                }
                $matchedArgs = ['query' => $cleanQuery];
            }
        }

        // Platform orders
        if ($matchedTool === null && (str_contains($lower, 'pesanan') || str_contains($lower, 'order')) && in_array('platform_get_orders', $toolNames, true)) {
            $matchedTool = 'platform_get_orders';
            if (str_contains($lower, 'menunggu pembayaran') || str_contains($lower, 'belum bayar') || str_contains($lower, 'awaiting_payment')) {
                $matchedArgs = ['status' => 'awaiting_payment'];
            } elseif (str_contains($lower, 'completed_at') || str_contains($lower, 'kosong') || str_contains($lower, 'null')) {
                $matchedArgs = ['status' => 'completed', 'null_completed_at' => true];
            } elseif (str_contains($lower, 'per toko') || str_contains($lower, 'tiap toko')) {
                $matchedArgs = ['status' => 'completed', 'period' => 'this_week', 'group_by_store' => true];
            }
        }

        // Platform couriers active tasks
        if ($matchedTool === null && str_contains($lower, 'kurir') && (str_contains($lower, 'tugas') || str_contains($lower, 'aktif')) && in_array('platform_get_courier_activity', $toolNames, true)) {
            $matchedTool = 'platform_get_courier_activity';
            $matchedArgs = ['only_active' => true];
        }

        // Platform settlements
        if ($matchedTool === null && (str_contains($lower, 'settlement') || str_contains($lower, 'pencairan')) && in_array('platform_get_settlements', $toolNames, true)) {
            $matchedTool = 'platform_get_settlements';
            $matchedArgs = ['status' => 'generated'];
        }

        // Platform explain admin menu
        if ($matchedTool === null && (str_contains($lower, 'menu') || str_contains($lower, 'fungsi')) && in_array('platform_explain_admin_menu', $toolNames, true)) {
            $matchedTool = 'platform_explain_admin_menu';
            $key = 'overview';
            if (str_contains($lower, 'pengguna') || str_contains($lower, 'user') || str_contains($lower, 'pelanggan')) $key = 'users';
            elseif (str_contains($lower, 'toko') || str_contains($lower, 'mitra')) $key = 'stores';
            elseif (str_contains($lower, 'pesanan') || str_contains($lower, 'order')) $key = 'orders';
            elseif (str_contains($lower, 'kurir')) $key = 'couriers';
            elseif (str_contains($lower, 'pembayaran')) $key = 'payments';
            elseif (str_contains($lower, 'refund')) $key = 'refunds';
            elseif (str_contains($lower, 'dispute') || str_contains($lower, 'sengketa') || str_contains($lower, 'komplain')) $key = 'disputes';
            elseif (str_contains($lower, 'audit')) $key = 'audit';
            elseif (str_contains($lower, 'komisi')) $key = 'commission';
            elseif (str_contains($lower, 'ai')) $key = 'ai';
            $matchedArgs = ['menu_key' => $key];
        }

        // Platform users
        if ($matchedTool === null && (str_contains($lower, 'user') || str_contains($lower, 'pengguna') || str_contains($lower, 'terdaftar') || (str_contains($lower, 'customer') && str_contains($lower, 'kurir')))) {
            if (in_array('platform_get_user_stats', $toolNames, true)) {
                $matchedTool = 'platform_get_user_stats';
            }
        }

        // Platform approvals
        if ($matchedTool === null && (str_contains($lower, 'approval') || str_contains($lower, 'verifikasi') || str_contains($lower, 'persetujuan') || str_contains($lower, 'pending'))) {
            if (in_array('platform_get_approvals', $toolNames, true)) {
                $matchedTool = 'platform_get_approvals';
            }
        }

        // Latest order or store (e.g. "terakhir pesen di toko apa", "pesanan terakhir", "toko terakhir")
        if ($matchedTool === null && (str_contains($lower, 'terakhir') || str_contains($lower, 'terbaru') || str_contains($lower, 'paling baru')) && (str_contains($lower, 'pesan') || str_contains($lower, 'pesen') || str_contains($lower, 'toko') || str_contains($lower, 'order') || str_contains($lower, 'laundry') || str_contains($lower, 'cucian'))) {
            if (in_array('customer_list_orders', $toolNames, true)) {
                $matchedTool = 'customer_list_orders';
                $matchedArgs = ['limit' => 1];
            }
        }

        // Order list / general orders
        if ($matchedTool === null && (str_contains($lower, 'pesanan') || str_contains($lower, 'cucian') || str_contains($lower, 'order') || str_contains($lower, 'lacak') || str_contains($lower, 'pesen'))) {
            if (str_contains($lower, 'berapa') || str_contains($lower, 'statistik') || str_contains($lower, 'jumlah') || str_contains($lower, 'ada berapa')) {
                if (in_array('customer_get_order_stats', $toolNames, true)) {
                    $matchedTool = 'customer_get_order_stats';
                }
            } else {
                if (in_array('customer_list_orders', $toolNames, true)) {
                    $matchedTool = 'customer_list_orders';
                } elseif (in_array('store_list_orders', $toolNames, true)) {
                    $matchedTool = 'store_list_orders';
                }
            }
        }

        // If a tool was matched, return a tool call!
        if ($matchedTool !== null) {
            return [
                'content' => null,
                'tool_calls' => [
                    [
                        'id' => 'call_' . uniqid(),
                        'name' => $matchedTool,
                        'arguments' => $matchedArgs,
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 15,
                ],
            ];
        }

        // General questions
        if (str_contains($lower, 'apa kabar') || str_contains($lower, 'kabar')) {
            return [
                'content' => 'Halo! Kabar baik. Ada yang bisa saya bantu terkait layanan laundry atau akun LaundryKu Anda?',
                'tool_calls' => [],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
            ];
        }

        // Fallback natural answer if no specific tool matched
        return [
            'content' => "Halo! Saya LANDY, asisten AI resmi LaundryKu. Saya siap membantu Anda memeriksa informasi pesanan, tarif layanan, informasi toko, dan akun Anda.",
            'tool_calls' => [],
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 25,
            ],
        ];
    }

    /**
     * Convert tool result JSON into a natural, factual Bahasa Indonesia response.
     */
    protected function formatToolResultToAnswer(array $data): string
    {
        if (isset($data['error'])) {
            return $data['error'];
        }

        // 1. User Profile
        if (isset($data['role']) && isset($data['name'])) {
            $roleLabel = $data['role'] === 'customer' ? 'Pelanggan' : ($data['role'] === 'store_admin' ? 'Admin Toko' : ($data['role'] === 'courier' ? 'Kurir' : $data['role']));
            $res = "Halo {$data['name']}! Anda terdaftar sebagai {$roleLabel} di LaundryKu.";
            if (!empty($data['email'])) $res .= " Email: {$data['email']}.";
            if (!empty($data['phone'])) $res .= " No. Telepon: {$data['phone']}.";
            if (isset($data['managed_store']) && is_array($data['managed_store'])) {
                $res .= " Toko yang Anda kelola: {$data['managed_store']['name']} ({$data['managed_store']['is_active']}, status: {$data['managed_store']['status']}) di {$data['managed_store']['city']}.";
            }
            if (isset($data['courier_status']) && is_array($data['courier_status'])) {
                $res .= " Status kurir: {$data['courier_status']['is_available']} dengan {$data['courier_status']['vehicle_type']} (plat: {$data['courier_status']['plate_number']}) di {$data['courier_status']['store_name']}.";
            }
            return $res;
        }

        // 2. Customer Order Stats
        if (isset($data['total_orders']) && isset($data['active_orders'])) {
            $res = "Anda memiliki total {$data['total_orders']} pesanan ({$data['active_orders']} aktif, {$data['completed_orders']} selesai) dengan total pengeluaran {$data['total_spent']}.";
            if (!empty($data['latest_order_number'])) {
                $res .= " Pesanan terakhir Anda adalah {$data['latest_order_number']} dengan status {$data['latest_order_status']}.";
            }
            return $res;
        }

        // 3. Customer Order Detail OR Store Order Detail
        if (isset($data['order_number']) && isset($data['weight_kg']) && isset($data['status_label'])) {
            $storeName = !empty($data['store_name']) ? " di {$data['store_name']}" : "";
            $custName = !empty($data['customer_name']) ? " (Pelanggan: {$data['customer_name']})" : "";
            return "Pesanan {$data['order_number']}{$storeName}{$custName} saat ini berstatus: {$data['status_label']}. Berat cucian: {$data['weight_kg']} kg, total biaya: {$data['total_amount']}, status bayar: {$data['payment_status']}.";
        }

        // 4. Customer List Orders OR Store List Orders
        if (isset($data['orders']) && isset($data['total_found'])) {
            if ($data['total_found'] === 0) {
                return "Belum ada pesanan yang ditemukan pada akun Anda.";
            }
            if (count($data['orders']) === 1) {
                $ord = $data['orders'][0];
                return "Pesanan terakhir Anda dilakukan di toko {$ord['store_name']} (No. Pesanan: {$ord['order_number']}) dengan status: {$ord['status_label']} dan total biaya {$ord['total_amount']}.";
            }
            $storeNameHeader = isset($data['store_name']) ? " di {$data['store_name']}" : "";
            $lines = ["Daftar pesanan{$storeNameHeader}:"];
            foreach ($data['orders'] as $ord) {
                $cust = !empty($ord['customer_name']) ? " (Pelanggan: {$ord['customer_name']})" : (!empty($ord['store_name']) ? " ({$ord['store_name']})" : "");
                $lines[] = "- {$ord['order_number']}{$cust}: {$ord['status_label']} — {$ord['total_amount']}";
            }
            return implode("\n", $lines);
        }

        // 5. Payment Status
        if (isset($data['order_payment_status']) && isset($data['total_bill'])) {
            $res = "Pesanan {$data['order_number']} memiliki status pembayaran: {$data['order_payment_status']} (Total tagihan: {$data['total_bill']}).";
            if (!empty($data['payment_record'])) {
                $res .= " Pembayaran via {$data['payment_record']['method']} sebesar {$data['payment_record']['amount']} ({$data['payment_record']['status']}).";
            }
            return $res;
        }

        // 6. Store Summary
        if (isset($data['store_name']) && isset($data['metrics'])) {
            $m = $data['metrics'];
            return "Ringkasan {$data['store_name']} ({$data['operational_status']}):\n" .
                "- Pesanan Baru: {$m['new_orders']}\n" .
                "- Sedang Diproses: {$m['processing_orders']}\n" .
                "- Siap Diantar: {$m['ready_for_delivery']}\n" .
                "- Selesai Hari Ini: {$m['completed_today']}\n" .
                "- Omzet Hari Ini: {$m['today_revenue']}\n" .
                "- Kurir Aktif: {$m['active_couriers']} dari {$m['total_couriers']} total kurir.";
        }

        // 7. Store Services
        if (isset($data['store_name']) && isset($data['services'])) {
            $lines = ["Daftar layanan {$data['store_name']}:"];
            foreach ($data['services'] as $s) {
                $lines[] = "- {$s['service_name']}: {$s['price_per_unit']} per {$s['unit']}";
            }
            return implode("\n", $lines);
        }

        // 8. Store Couriers
        if (isset($data['store_name']) && isset($data['couriers'])) {
            if (empty($data['couriers'])) {
                return "Belum ada kurir yang terhubung ke toko {$data['store_name']}.";
            }
            $lines = ["Daftar kurir {$data['store_name']} ({$data['total_couriers']} kurir):"];
            foreach ($data['couriers'] as $c) {
                $lines[] = "- {$c['name']} ({$c['vehicle_type']}): {$c['is_available']} | No: {$c['phone']}";
            }
            return implode("\n", $lines);
        }

        // 9. Store Reviews
        if (isset($data['average_rating']) && isset($data['recent_reviews'])) {
            $res = "Toko {$data['store_name']} memiliki rating rata-rata {$data['average_rating']} dari {$data['review_count']} ulasan.";
            if (!empty($data['recent_reviews'])) {
                $res .= "\nUlasan terbaru:";
                foreach ($data['recent_reviews'] as $r) {
                    $res .= "\n- [Bintang {$r['rating']}] {$r['customer_name']}: \"{$r['comment']}\"";
                }
            }
            return $res;
        }

        // 10. Courier Profile
        if (isset($data['vehicle_type']) && isset($data['plate_number'])) {
            return "Profil Kurir: {$data['name']} terafiliasi dengan {$data['store_name']}. Kendaraan: {$data['vehicle_type']} (Plat: {$data['plate_number']}), Status: {$data['is_available']}.";
        }

        // 11. Courier Tasks
        if (isset($data['tasks']) && isset($data['total_tasks'])) {
            if ($data['total_tasks'] === 0) {
                return "Tidak ada tugas kurir yang aktif saat ini.";
            }
            $lines = ["Daftar tugas kurir Anda ({$data['total_tasks']} tugas):"];
            foreach ($data['tasks'] as $t) {
                $lines[] = "- Pesanan {$t['order_number']}: {$t['task_type']} ({$t['status']})";
            }
            return implode("\n", $lines);
        }

        // 12. Courier Task Stats
        if (isset($data['active_tasks']) && isset($data['completed_tasks_today'])) {
            return "Statistik Kurir: {$data['active_tasks']} tugas aktif, {$data['completed_tasks_today']} tugas selesai hari ini, dan total {$data['total_completed_lifetime']} tugas selesai secara keseluruhan.";
        }

        // 13. Courier COD Rules
        if (isset($data['max_cod_amount']) && isset($data['rules'])) {
            $lines = ["Ketentuan Cash on Delivery (COD) Kurir (Batas Maksimal: {$data['max_cod_amount']}):"];
            foreach ($data['rules'] as $rule) {
                $lines[] = "- {$rule}";
            }
            return implode("\n", $lines);
        }

        // 14. Platform Overview
        if (isset($data['gmv_today']) && isset($data['orders_today']) && isset($data['stores_total'])) {
            return "Ringkasan Platform LaundryKu:\n" .
                "- GMV Hari Ini: {$data['gmv_today']}\n" .
                "- Pesanan Hari Ini: {$data['orders_today']}\n" .
                "- Pesanan Berjalan: {$data['orders_in_progress']}\n" .
                "- Toko: {$data['stores_total']} total ({$data['stores_active']} aktif, {$data['stores_pending']} menunggu approval)\n" .
                "- Pelanggan: {$data['customers_total']} | Kurir: {$data['couriers_total']}.";
        }

        // 15. Platform User Stats
        if (isset($data['total_users']) && isset($data['customers'])) {
            return "Statistik Pengguna Platform LaundryKu:\n" .
                "- Total Pengguna: {$data['total_users']}\n" .
                "- Pelanggan: {$data['customers']}\n" .
                "- Pengelola Toko: {$data['store_managers']}\n" .
                "- Kurir: {$data['couriers']}.";
        }

        // 16. Platform Approvals
        if (isset($data['pending_count']) && isset($data['stores_awaiting_approval'])) {
            if ($data['pending_count'] === 0) {
                return "Tidak ada toko mitra yang sedang menunggu persetujuan (approval).";
            }
            $lines = ["Toko mitra menunggu persetujuan ({$data['pending_count']} toko):"];
            foreach ($data['stores_awaiting_approval'] as $s) {
                $lines[] = "- {$s['name']} ({$s['city']}) — Diajukan: {$s['submitted_at']}";
            }
            return implode("\n", $lines);
        }

        // 17. Complaints
        if (isset($data['total_complaints']) && isset($data['complaints'])) {
            if ($data['total_complaints'] === 0) {
                return "Tidak ada komplain atau dispute yang tercatat.";
            }
            $lines = ["Daftar komplain ({$data['total_complaints']}):"];
            foreach ($data['complaints'] as $c) {
                $lines[] = "- Pesanan {$c['order_number']}: \"{$c['reason']}\" (Status: {$c['status']})";
            }
            return implode("\n", $lines);
        }

        // 18. Platform Search Users
        if (isset($data['users']) && isset($data['total'])) {
            if ($data['total'] === 0) {
                return $data['message'] ?? 'Pengguna tidak ditemukan.';
            }
            $lines = ["Hasil pencarian pengguna ({$data['total']} akun):"];
            foreach ($data['users'] as $u) {
                $lines[] = "- {$u['name']} (Role: {$u['role']}, Email: {$u['masked_email']}, Telepon: {$u['masked_phone']}, Status: {$u['status']}, {$u['linkage']})";
            }
            return implode("\n", $lines);
        }

        // 19. Platform Orders
        if (isset($data['breakdown'])) {
            if (empty($data['breakdown'])) {
                return "Tidak ada pesanan pada periode ini.";
            }
            $lines = ["Jumlah pesanan selesai minggu ini per toko:"];
            foreach ($data['breakdown'] as $b) {
                $lines[] = "- {$b['store_name']}: {$b['count']} pesanan";
            }
            return implode("\n", $lines);
        }
        if (isset($data['orders']) && isset($data['total'])) {
            if ($data['total'] === 0) {
                return "Tidak ada pesanan yang sesuai kriteria.";
            }
            $lines = ["Daftar pesanan ({$data['total']} pesanan):"];
            foreach ($data['orders'] as $o) {
                $lines[] = "- {$o['order_number']} (Toko: {$o['store_name']}, Pelanggan: {$o['customer_name']}, Status: {$o['status']}, Total: Rp " . number_format($o['total_amount'], 0, ',', '.') . ")";
            }
            return implode("\n", $lines);
        }

        // 20. Platform Courier Activity
        if (isset($data['active_tasks'])) {
            if (empty($data['active_tasks'])) {
                return "Saat ini tidak ada kurir yang sedang memiliki tugas aktif.";
            }
            $lines = ["Daftar kurir dengan tugas aktif:"];
            foreach ($data['active_tasks'] as $t) {
                $lines[] = "- {$t['courier_name']} (Toko: {$t['store_name']}): Tugas {$t['task_type']} untuk pesanan {$t['order_number']} (Status: {$t['status']})";
            }
            return implode("\n", $lines);
        }

        // 21. Platform Settlements
        if (isset($data['status']) && isset($data['total_settlements']) && isset($data['formatted_nominal'])) {
            if ($data['total_settlements'] === 0) {
                return "Saat ini tidak ada pengajuan settlement dengan status {$data['status']}.";
            }
            $lines = ["Terdapat {$data['total_settlements']} pengajuan settlement ({$data['status']}) dengan total {$data['formatted_nominal']}:"];
            foreach ($data['items'] as $item) {
                $lines[] = "- {$item['reference']} (Toko: {$item['store_name']}, Periode: {$item['period']}, Nominal: Rp " . number_format($item['net_amount'], 0, ',', '.') . ")";
            }
            return implode("\n", $lines);
        }

        // 22. Platform Explain Admin Menu
        if (isset($data['explanation'])) {
            return $data['explanation'];
        }
        if (isset($data['menus'])) {
            $lines = [$data['overview'] ?? 'Menu Admin:'];
            foreach ($data['menus'] as $k => $desc) {
                $lines[] = "- " . ucfirst($k) . ": {$desc}";
            }
            return implode("\n", $lines);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}