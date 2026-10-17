<?php

namespace App\Services;

use App\Models\CourierStoreAssignment;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatService
{
    protected string $provider;
    protected ?string $apiKey;
    protected string $baseUrl;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->provider = (string) (config('ai.provider') ?: config('services.ai.provider', 'openrouter'));
        $servicesKey = config('services.ai.api_key');
        $aiKey = config('ai.openai.api_key');
        $this->apiKey = (!empty($servicesKey) && !in_array($servicesKey, ['dummy_test_ai_key', 'dummy_openai_api_key_placeholder', 'dummy_openrouter_api_key_placeholder'], true))
            ? $servicesKey
            : ($aiKey ?: $servicesKey);
        $this->baseUrl = rtrim((string) (config('ai.openai.base_url') ?: config('services.ai.base_url', 'https://openrouter.ai/api/v1')), '/');
        $this->model = (string) (config('ai.openai.model') ?: config('services.ai.model', 'google/gemma-4-26b-a4b-it:free'));
        $this->timeout = (int) (config('ai.openai.timeout') ?: config('services.ai.timeout', 15));
    }

    /**
     * Check whether AI mock response mode is strictly active.
     * Mock is only active if mock_in_local is true AND app environment is strictly local.
     */
    public function isMockActive(): bool
    {
        return (bool) config('ai.mock_in_local', false) && app()->environment('local');
    }

    /**
     * Send a prompt or conversation history to the AI provider and return the response.
     *
     * @param array $messages Array of ['role' => 'user'|'assistant'|'system', 'content' => string]
     * @param User|null $user The authenticated user context
     * @return array ['content' => string, 'provider' => string, 'is_fallback' => bool, 'token_count' => int|null]
     */
    public function chat(array $messages, ?User $user = null): array
    {
        $provider = (string) (config('ai.provider') ?: config('services.ai.provider', $this->provider));
        $servicesKey = config('services.ai.api_key');
        $aiKey = config('ai.openai.api_key');
        $apiKey = (!empty($servicesKey) && !in_array($servicesKey, ['dummy_test_ai_key', 'dummy_openai_api_key_placeholder', 'dummy_openrouter_api_key_placeholder'], true))
            ? $servicesKey
            : ($aiKey ?: $servicesKey ?: $this->apiKey);
        $baseUrl = rtrim((string) (config('ai.openai.base_url') ?: config('services.ai.base_url', $this->baseUrl)), '/');
        $baseUrl = str_replace('openrouter.io', 'openrouter.ai', $baseUrl);
        $model = (string) (config('ai.openai.model') ?: config('services.ai.model', $this->model));
        $timeout = (int) (config('ai.openai.timeout') ?: config('services.ai.timeout', $this->timeout));

        $userQuestion = '';
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $userQuestion = $msg['content'] ?? '';
                break;
            }
        }

        // 1. Safety Content Filter Check (Block dangerous / harmful / illegal / exploit content)
        $bannedCategory = self::detectOutOfScope($userQuestion);
        if ($bannedCategory !== null) {
            if ($bannedCategory === 'prompt_injection' || $bannedCategory === 'secret_leak') {
                return [
                    'content' => 'Maaf, saya adalah asisten informasi yang bersifat read-only dan tidak dapat membagikan kredensial rahasia, token, database, ataupun mengubah instruksi sistem platform LaundryKu.',
                    'provider' => 'security_filter',
                    'is_fallback' => false,
                    'token_count' => null,
                ];
            }

            return [
                'content' => 'Maaf, saya tidak dapat memberikan jawaban atau instruksi terkait hal berbahaya, ilegal, senjata, obat terlarang, self-harm, peretasan, atau konten tidak pantas lainnya. Silakan tanyakan hal seputar layanan LaundryKu, saya siap membantu!',
                'provider' => 'content_filter',
                'is_fallback' => false,
                'token_count' => null,
            ];
        }

        // 2. Build System Prompt with Grounded Read-Only Context
        $systemPrompt = self::buildSystemPrompt($user);

        // Prepare full messages array for OpenAI-compatible API
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            if (in_array($role, ['user', 'assistant'])) {
                $apiMessages[] = [
                    'role' => $role,
                    'content' => (string) ($msg['content'] ?? ''),
                ];
            }
        }

        $isMock = $this->isMockActive();
        $isKeyConfigured = !empty($apiKey) && strlen($apiKey) >= 10 && !str_contains($apiKey, '...') && !in_array($apiKey, ['dummy_openai_api_key_placeholder', 'dummy_openrouter_api_key_placeholder', 'dummy_test_ai_key'], true);

        // 3. If API key is not configured or invalid/truncated
        if (!$isKeyConfigured) {
            error_log(sprintf('[AiChatService Warning] API Key tidak terkonfigurasi dengan benar atau terpotong (panjang: %d). Fallback ke mode aman.', strlen($apiKey ?? '')));
            if ($isMock) {
                $fallbackReply = $this->generateFallbackResponse($userQuestion, $user, $messages);

                return [
                    'content' => $fallbackReply,
                    'provider' => 'local_fallback',
                    'is_fallback' => true,
                    'token_count' => null,
                ];
            }

            return [
                'content' => 'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.',
                'provider' => 'fallback',
                'is_fallback' => true,
                'token_count' => null,
            ];
        }

        // 4. Attempt calling AI Provider with primary and fallback models
        $fallbackModels = (array) config('ai.openai.fallback_models', []);
        $modelsToTry = array_values(array_unique(array_filter([$model, ...$fallbackModels])));
        $httpReferer = (string) config('ai.http_referer', config('app.url', 'http://localhost:8000'));
        $xTitle = (string) config('ai.x_title', config('app.name', 'LaundryKu'));

        foreach ($modelsToTry as $currentModel) {
            try {
                $targetEndpoint = $baseUrl . '/chat/completions';
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => $httpReferer,
                        'X-Title' => $xTitle,
                    ])
                    ->post($targetEndpoint, [
                        'model' => $currentModel,
                        'messages' => $apiMessages,
                        'temperature' => 0.4,
                        'max_tokens' => 1200,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $reply = $data['choices'][0]['message']['content'] ?? null;
                    $tokenCount = $data['usage']['total_tokens'] ?? null;

                    if (!empty($reply)) {
                        return [
                            'content' => trim($reply),
                            'provider' => $provider,
                            'is_fallback' => false,
                            'token_count' => $tokenCount,
                        ];
                    }
                }

                $status = $response->status();
                $bodyPreview = substr((string) $response->body(), 0, 500);

                Log::error('AI Provider returned non-successful response', [
                    'status' => $status,
                    'url' => $targetEndpoint,
                    'model' => $currentModel,
                    'provider' => $provider,
                    'error_snippet' => $bodyPreview,
                ]);
                error_log(sprintf('[AiChatService Error] Non-OK Status: %d | Endpoint: %s | Model: %s | Body: %s', $status, $targetEndpoint, $currentModel, $bodyPreview));

                // Try next model if current model is rate-limited (429) or unavailable (404/503)
                if (in_array($status, [404, 429, 503], true)) {
                    continue;
                }

                // For 401 (bad key) or other terminal errors, don't waste time trying more models
                break;
            } catch (Throwable $e) {
                Log::error('AI Provider API call failed: ' . $e->getMessage(), [
                    'provider' => $provider,
                    'model' => $currentModel,
                    'url' => $baseUrl . '/chat/completions',
                ]);
                error_log(sprintf('[AiChatService Exception] Endpoint: %s/chat/completions | Model: %s | Error: %s', $baseUrl, $currentModel, $e->getMessage()));
            }
        }

        // 5. Provider failure fallback
        if ($isMock) {
            $fallbackReply = $this->generateFallbackResponse($userQuestion, $user, $messages);

            return [
                'content' => $fallbackReply,
                'provider' => 'local_fallback',
                'is_fallback' => true,
                'token_count' => null,
            ];
        }

        return [
            'content' => 'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.',
            'provider' => 'fallback',
            'is_fallback' => true,
            'token_count' => null,
        ];
    }

    /**
     * Safety & Security Filter: detects harmful/illegal content and prompt injections.
     */
    public static function detectOutOfScope($userMessage): ?string
    {
        $bannedPatterns = [
            // Dangerous content & weapons
            '/\b(cara|tutorial|instruksi)\s+(membuat|bikin).*(bom|senjata|narkoba|racun|poison|eksplosif|peledak)/i' => 'dangerous_content',
            // Self-harm
            '/\b(cara|bagaimana|gimana).*(bunuh diri|self.?harm|cutting|luka diri|gantung diri)/i' => 'self_harm',
            // Hacking/Illegal system exploitation
            '/\b(hacking|exploit|crack|bypass|inject|ddos|sql\s*injection).*(sistem|akun|server|password|bank|atm|kartu kredit)/i' => 'illegal_activity',
            // NSFW / Pornography
            '/\b(buat|bikin|tonton|liat|video|gambar|konten).*(porno|nsfw|dewasa|18\+|bokep|sex)/i' => 'nsfw_content',
            // Prompt Injection & Secret Extraction
            '/(ignore\s+all\s+previous\s+instructions|ignore\s+system\s+prompt|tampilkan\s+api_key|bocorkan\s+password|show\s+secret|give\s+me\s+root\s+password)/i' => 'prompt_injection',
            '/(minta\s+otp|kirim\s+otp\s+kamu|sebutkan\s+otp\s+rahasia|database\s+password|secret\s+key)/i' => 'secret_leak',
        ];

        foreach ($bannedPatterns as $pattern => $category) {
            if (preg_match($pattern, (string) $userMessage)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Build comprehensive system instructions with grounded read-only context.
     */
    public static function buildSystemPrompt($userContext = null): string
    {
        $prompt = <<<'PROMPT'
IDENTITAS & PERAN:
Kamu adalah "Asisten LaundryKu", asisten virtual platform LaundryKu yang ramah, santai, dan solutif. Tugasmu adalah membantu pelanggan (customer), mitra toko laundry, dan kurir dalam memahami layanan, alur penggunaan aplikasi, menyelesaikan kendala, serta bernavigasi di platform LaundryKu.

GAYA KOMUNIKASI & MULTI-TURN:
- Gunakan Bahasa Indonesia yang natural, santai, sopan, bersahabat, dan jelas.
- Asisten terbuka diajak ngobrol bebas seputar platform LaundryKu: siapa kamu, apa saja yang bisa dibantu, siapa pengguna yang sedang login, peran/role pengguna, mode aktif, cara pakai aplikasi, panduan akun, cara pemesanan, pembayaran, mitra, kurir, kebijakan platform, dsb.
- Pahami maksud pengguna berdasarkan bahasa natural dan konteks percakapan multi-turn, meskipun pertanyaannya singkat, informal, mengandung typo, atau bahasa sehari-hari.
- JANGAN mewajibkan pengguna menggunakan kata kunci atau format kaku.
- JANGAN PERNAH hanya menjawab kaku seperti "bisa dijelaskan lebih spesifik?" jika maksud pengguna sudah dapat dipahami. Berikan jawaban awal yang jelas dan bermanfaat terlebih dahulu!

PENGETAHUAN SISTEM LAUNDRYKU BERDASARKAN ROLE (SESUAI PRD):
1. ROLE CUSTOMER (PELANGGAN):
   - Alur Order: Buka menu Cari Toko (/stores) dalam radius 10 km -> pilih layanan -> konfirmasi alamat jemput -> kurir menjemput cucian (bisa dipantau live tracking peta) -> pakaian ditimbang riil di toko -> notifikasi harga final & berat riil -> customer menyetujui harga final di menu Pesanan Saya (/orders) -> bayar (QRIS, VA, COD batas 1 jam) -> proses cuci (washing -> drying -> ironing) -> siap antar -> kurir antar pakaian bersih -> customer terima & beri rating.
   - Hak Pembatalan: Bebas biaya di tahap persetujuan harga timbangan (AWAITING_PRICE_APPROVAL). Jika sudah dicuci (PROCESSING), koordinasi via Chat Toko (/chat).
   - Mode Switcher: Pelanggan yang juga mendaftar dan disetujui sebagai mitra toko atau kurir dapat berganti mode aktif (Customer, Store, Courier) di profil atau switcher mode (/me/mode).

2. ROLE STORE ADMIN / MITRA TOKO:
   - Akses: Dashboard Toko (/store) untuk mengelola outlet usaha laundry.
   - Tugas & Fitur: Mengatur paket layanan & harga per kg (/store/services), menginput timbangan riil saat cucian tiba di outlet (WEIGHED) agar harga final terbit, merekrut dan menugaskan kurir toko (/store-admin/couriers), mengatur status buka/tutup toko, serta melihat laporan keuangan & pembukuan di menu Ledger Toko (/store-admin/ledger).

3. ROLE COURIER (KURIR):
   - Akses: Antarmuka Tugas Kurir (/courier/tasks).
   - Tugas & Fitur: Menerima tugas penjemputan cucian (pickup) dari alamat customer ke toko mitra, dan tugas pengantaran cucian bersih (delivery) dari toko ke customer. Mengunggah bukti serah terima (foto), memperbarui status perjalanan penugasan, serta menerima & mengonfirmasi penerimaan uang pembayaran tunai (COD) langsung ke sistem saat serah terima.

4. ROLE SUPER ADMIN:
   - Akses: Admin Panel (/admin).
   - Tugas & Fitur: Verifikasi pendaftaran mitra toko baru (approve/reject), memantau seluruh transaksi pesanan, pembayaran & refund via payment gateway Xendit lintas toko, mengatur komisi platform, moderasi akun, dan memeriksa audit log sistem.

5. STATUS PESANAN RESMI:
   - PENDING_PICKUP, PICKUP_ASSIGNED, PICKED_UP, AT_STORE, WEIGHED, AWAITING_PRICE_APPROVAL, PRICE_APPROVED, AWAITING_PAYMENT, PAID, PROCESSING, READY_FOR_DELIVERY, DELIVERY_ASSIGNED, OUT_FOR_DELIVERY, COMPLETED, CANCELLED, EXPIRED_UNPAID.

6. PEMBAYARAN & EXPIRED 1 JAM:
   - Metode: QRIS instan, Virtual Account bank via Xendit, COD tunai ke kurir.
   - Batas Waktu Bayar: Tepat 1 JAM (60 MENIT). Jika lewat 1 jam belum dibayar, tagihan otomatis kedaluwarsa (EXPIRED_UNPAID), pesanan batal, dan cucian masuk proses pengembalian oleh kurir.

7. REGISTRASI & LOGOUT:
   - Daftar & Login: Passwordless OTP via WhatsApp/Email (6 digit, aktif 5 menit) di /auth.
   - Cara Logout: Buka menu Profil (/profile) atau avatar kanan atas -> klik tombol Keluar / Logout.

8. PENDAFTARAN MITRA TOKO & KURIR:
   - Daftar Mitra Toko: Menu Gabung Mitra (/upgrade) -> tab Pendaftaran Toko (KTP, foto outlet fisik, titik GPS 10 km, jam buka, WA). Verifikasi 1x24 jam kerja.
   - Daftar Kurir: Menu Gabung Mitra (/upgrade) -> tab Pengajuan Kurir (KTP, SIM C aktif, motor & plat nomor, WA). Verifikasi 1x24 jam kerja.

ATURAN KERAS & BATASAN (STRICT GUARDRAILS):
1. JANGAN MENGARANG ETA KEDATANGAN KURIR: Jangan menyebutkan perkiraan waktu dalam menit atau jam (misal: "kurir tiba dalam 15 menit"). Katakan tergantung rute dan arahkan ke live tracking di /orders atau /chat.
2. JANGAN MENGARANG TARIF ATAU JAM OPERASIONAL TOKO: Tarif dan jadwal buka tiap outlet berbeda. Arahkan pengguna memeriksa kartu profil toko di /stores atau bertanya via Chat Toko (/chat).
3. DATA PRIBADI & PESANAN HANYA UNTUK USER LOGIN:
   - Jika pengguna bertanya tentang siapa dirinya, peran, mode aktif, toko miliknya, atau tugasnya: jawab berdasarkan Konteks Pengguna Login. JANGAN PERNAH membocorkan data pengguna lain.
   - Jika pengguna belum login (status Tamu), jelaskan dengan ramah bahwa untuk melihat data pribadi mereka harus masuk/login terlebih dahulu di /auth.
4. JANGAN MENJANJIKAN REFUND DI LUAR PRD: Refund hanya jika batal sebelum cuci atau komplain disetujui via Xendit 1-3 hari kerja.
5. JIKA TIDAK TAHU: Katakan jujur dan arahkan ke CS resmi di /profile atau Chat Toko (/chat).
6. HANYA MENJAWAB TOPIK LAUNDRYKU & LARANGAN MENULIS KODE:
   - Kamu HANYA boleh menjawab topik yang berkaitan langsung dengan platform LaundryKu (cara pesan, estimasi harga/tarif, metode pembayaran, status/pelacakan cucian, kebijakan komplain/refund, pendaftaran mitra toko atau kurir, dan kontak bantuan).
   - Untuk topik di luar LaundryKu (seperti coding/pemrograman python/javascript/dsb, pengetahuan umum, politik/presiden, resep masakan, identitas model dasar, dll.): TOLAK DENGAN SOPAN dan arahkan balik ke topik LaundryKu.
   - JANGAN PERNAH menulis, membuatkan, atau mengoreksi kode program apa pun atas permintaan pengguna. Gunakan jawaban penolakan ramah: "Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Mau tanya soal itu?"
7. BERSIFAT READ-ONLY: Asisten tidak dapat mengubah database secara langsung. Berikan instruksi langkah mandiri.
PROMPT;

        if ($userContext instanceof User) {
            $prompt .= "\n" . (new static())->getUserScopedContext($userContext);
        } elseif (is_string($userContext) && !empty($userContext)) {
            $prompt .= "\n" . $userContext;
        } else {
            $prompt .= "\nStatus Pengguna: Tamu / Pengunjung Belum Login.";
        }

        return $prompt;
    }

    /**
     * Normalizes colloquial Indonesian slang, abbreviations, and common typos.
     */
    public static function normalizeQuery(string $text): string
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()?"\'’]/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $slangMap = [
            'blm' => 'belum',
            'blom' => 'belum',
            'belom' => 'belum',
            'dateng' => 'datang',
            'dtg' => 'datang',
            'nyampe' => 'sampai',
            'sampe' => 'sampai',
            'batalin' => 'batal',
            'cancelin' => 'batal',
            'cancel' => 'batal',
            'ga' => 'tidak',
            'gak' => 'tidak',
            'ngga' => 'tidak',
            'nggak' => 'tidak',
            'ndak' => 'tidak',
            'gue' => 'saya',
            'gw' => 'saya',
            'gua' => 'saya',
            'aku' => 'saya',
            'sy' => 'saya',
            'lu' => 'kamu',
            'lo' => 'kamu',
            'elu' => 'kamu',
            'laundryan' => 'cucian',
            'londri' => 'laundry',
            'loundry' => 'laundry',
            'cucian' => 'laundry',
            'duit' => 'uang',
            'fulus' => 'uang',
            'ongkos' => 'uang',
            'balikin' => 'refund',
            'balik' => 'refund',
            'kembali' => 'refund',
            'hub' => 'hubungi',
            'hbngi' => 'hubungi',
            'kontak' => 'hubungi',
            'telpon' => 'telepon',
            'tlp' => 'telepon',
            'baju' => 'pakaian',
            'kaos' => 'pakaian',
            'celana' => 'pakaian',
            'sobek' => 'rusak',
            'robek' => 'rusak',
            'rusakk' => 'rusak',
            'ilang' => 'hilang',
            'lelet' => 'lama',
            'lambat' => 'lama',
            'telat' => 'lama',
            'almt' => 'alamat',
            'gmn' => 'gimana',
            'bgmn' => 'bagaimana',
            'bs' => 'bisa',
            'syrt' => 'syarat',
            'verif' => 'verifikasi',
            'acc' => 'disetujui',
        ];

        $words = explode(' ', (string) $normalized);
        $mapped = array_map(fn($w) => $slangMap[$w] ?? $w, $words);
        return implode(' ', $mapped);
    }

    /**
     * Fetch strictly read-only scoped user context, role, active mode, stores, and courier tasks.
     */
    public function getUserScopedContext(?User $user): string
    {
        if (!$user) {
            return "Status Pengguna: Tamu / Pengunjung Belum Login.";
        }

        $roles = $user->roles->pluck('code')->toArray();
        $rolesStr = !empty($roles) ? implode(', ', $roles) : 'customer';
        $activeMode = $user->active_mode ?: 'customer';
        $availableModes = method_exists($user, 'getAvailableModes') ? $user->getAvailableModes() : [$activeMode];
        $availableModesStr = implode(', ', $availableModes);

        $lines = [
            "Konteks Pengguna Login:",
            "- Nama: {$user->name}",
            "- ID Pengguna: {$user->id}",
            "- Role Terdaftar: {$rolesStr}",
            "- Mode Aktif: {$activeMode}",
            "- Mode Tersedia: {$availableModesStr}",
        ];

        // Store Admin / Owner Context
        $stores = Store::where('owner_user_id', $user->id)
            ->where('approval_status', 'approved')
            ->get();
        if ($stores->isEmpty() && !empty($roles) && in_array('store_admin', $roles, true)) {
            $storeIds = $user->roles()->whereNotNull('user_roles.store_id')->pluck('user_roles.store_id');
            if ($storeIds->isNotEmpty()) {
                $stores = Store::whereIn('id', $storeIds)
                    ->where('approval_status', 'approved')
                    ->get();
            }
        }

        if ($stores->isNotEmpty()) {
            $lines[] = "Toko Laundry yang Dikelola Pengguna Ini:";
            foreach ($stores as $st) {
                $statusApprove = strtoupper($st->approval_status ?? 'APPROVED');
                $statusOp = strtoupper($st->operational_status ?? 'ACTIVE');
                $lines[] = "  * {$st->name} (Alamat: {$st->address_text}, Status Approval: {$statusApprove}, Operasional: {$statusOp})";
            }
        }

        // Courier Assignment Context
        $assignment = CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->with('store')
            ->first();

        if ($assignment && $assignment->store) {
            $lines[] = "Penugasan Kurir Aktif: Bertugas di toko '{$assignment->store->name}' (Status Penugasan: Aktif).";

            $courierTasks = Order::where('courier_id', $user->id)
                ->whereIn('status', ['PICKUP_ASSIGNED', 'PICKED_UP', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY'])
                ->with('store')
                ->take(5)
                ->get();

            if ($courierTasks->isNotEmpty()) {
                $lines[] = "Daftar Tugas Penjemputan / Pengantaran Kurir yang Sedang Berjalan:";
                foreach ($courierTasks as $task) {
                    $taskType = in_array($task->status, ['PICKUP_ASSIGNED', 'PICKED_UP'], true) ? 'Penjemputan (Pickup)' : 'Pengantaran (Delivery)';
                    $stName = $task->store->name ?? 'Toko';
                    $lines[] = "  * No: {$task->order_number} | Tipe: {$taskType} | Toko: {$stName} | Status: {$task->status}";
                }
            } else {
                $lines[] = "Saat ini tidak ada tugas penjemputan/pengantaran aktif yang sedang berjalan.";
            }
        }

        // Super Admin Context
        if ($user->hasRole('super_admin')) {
            $lines[] = "Hak Akses Platform: Super Admin (Pengelola seluruh ekosistem platform LaundryKu).";
        }

        // Customer recent orders
        $orders = Order::where('customer_id', $user->id)
            ->with(['store', 'items'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        if ($orders->isNotEmpty()) {
            $lines[] = "Daftar 5 Pesanan Terakhir Anda di LaundryKu:";
            foreach ($orders as $order) {
                $storeName = $order->store->name ?? 'Toko Laundry';
                $statusLabel = $this->translateStatus($order->status);
                $paymentStatus = strtoupper($order->payment_status ?? 'UNPAID');
                $estAmount = 'Rp ' . number_format($order->estimated_amount ?? 0, 0, ',', '.');
                $finalAmount = $order->final_amount ? 'Rp ' . number_format($order->final_amount, 0, ',', '.') : 'Menunggu penimbangan';
                $date = $order->created_at ? $order->created_at->format('d M Y H:i') : '-';

                $lines[] = "- No: {$order->order_number} | Toko: {$storeName} | Tgl: {$date} | Status: {$statusLabel} ({$order->status}) | Bayar: {$paymentStatus} | Estimasi: {$estAmount} | Final: {$finalAmount}";
            }
        } elseif (!$user->hasRole('store_admin') && !$user->hasRole('courier') && !$user->hasRole('super_admin')) {
            $lines[] = "Belum memiliki riwayat pesanan aktif.";
        }

        return implode("\n", $lines);
    }

    /**
     * Comprehensive Fallback Engine: Answers all queries with exact LaundryKu knowledge.
     */
    public function generateFallbackResponse(string $question, ?User $user = null, array $history = []): string
    {
        $rawQ = strtolower(trim($question));
        $q = self::normalizeQuery($question);

        // 1. Safety / Banned Check
        if (self::detectOutOfScope($q) !== null) {
            return "Maaf, saya tidak dapat memberikan jawaban atau instruksi terkait hal berbahaya, ilegal, senjata, obat terlarang, self-harm, peretasan, atau kredensial rahasia.";
        }

        // 2. Pure Greeting Check (Only when it's purely a greeting)
        if (preg_match('/^(halo|hai|hi|hey|assalam(u[\'\’]?alaikum)?|selamat\s+(pagi|siang|sore|malam)|halo\s+min|p)[\s.!]*$/i', $rawQ)) {
            return "Halo! Ada yang bisa saya bantu seputar pemesanan laundry, status pesanan, pembayaran, atau kemitraan di LaundryKu hari ini?";
        }

        // 2b. Identitas Bot ("kamu siapa", "siapa kamu", "siapa anda", "kamu asisten apa", "tentang kamu")
        if (preg_match('/\b(kamu|anda|bot|asisten)\s+(siapa|apa)\b/i', $q) ||
            preg_match('/\b(siapa|apa)\s+(kamu|anda|asisten|bot)\b/i', $q) ||
            str_contains($q, 'identitas') || str_contains($q, 'tentang kamu')) {
            return "Halo! Saya adalah **Asisten LaundryKu**, asisten virtual resmi platform LaundryKu. Saya siap membantu pelanggan, mitra toko laundry, dan kurir seputar penggunaan platform—mulai dari alur pemesanan laundry, status pesanan, metode pembayaran (QRIS, VA, COD), pendaftaran mitra atau kurir, hingga navigasi fitur aplikasi.\n\nAda yang bisa saya bantu untuk Anda hari ini?";
        }

        // 2b-2. Explicit Out-of-Scope Off-Topic Rejection (Coding, general knowledge, politics, etc.)
        if (preg_match('/\b(coding|koding|ngoding|code|python|javascript|php|html|css|java|c\+\+|golang|sql|script|program|pemrograman|bikin program|buatkan coding|buatkan kode|tulis kode|presiden|menteri|politik|pemilu|pilpres|resep|masak|makanan|cuaca|saham|crypto|bitcoin|judi|slot|game|film|selebriti|artis|chord|gitar|fisika|matematika|sejarah|biologi)\b/i', $q)) {
            return "Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?";
        }

        // 2c. Kemampuan Bot ("bisa bantu apa", "kamu bisa apa", "apa yang bisa dibantu", "bisa ngapain")
        if (str_contains($q, 'bisa bantu apa') || str_contains($q, 'bantu apa') ||
            str_contains($q, 'apa yang bisa dibantu') || str_contains($q, 'kamu bisa apa') ||
            str_contains($q, 'bisa ngapain') || str_contains($q, 'fitur apa saja')) {
            return "Sebagai **Asisten LaundryKu**, saya dapat membantu Anda dalam berbagai hal seputar platform LaundryKu:\n\n" .
                "1. **Pemesanan Laundry:** Panduan mencari toko laundry terdekat di menu **Cari Toko** (`/stores`), memilih jenis layanan (Kiloan, Satuan, Express), dan alur penjemputan cucian.\n" .
                "2. **Status & Pelacakan Pesanan:** Menjelaskan tahapan pesanan di menu **Pesanan Saya** (`/orders`) (penjemputan kurir, penimbangan riil toko, persetujuan harga timbangan, cuci, hingga pengantaran).\n" .
                "3. **Pembayaran & Batas Waktu:** Info metode pembayaran (QRIS instan, Virtual Account, COD) dan batas waktu bayar 1 jam (60 menit).\n" .
                "4. **Kemitraan & Kurir:** Cara mendaftar menjadi mitra toko laundry atau kurir di menu **Gabung Mitra** (`/upgrade`).\n" .
                "5. **Akun & Bantuan:** Panduan login OTP tanpa password di `/auth`, cara logout di menu **Profil** (`/profile`), pengajuan komplain garansi (maks 1x24 jam), serta bantuan refund.\n\n" .
                "Silakan tanyakan hal yang ingin Anda ketahui seputar LaundryKu!";
        }

        // 2d. Cara Logout ("cara logout", "cara keluar", "keluar akun", "logout")
        if (str_contains($q, 'logout') || str_contains($q, 'sign out') ||
            (str_contains($q, 'keluar') && (str_contains($q, 'akun') || str_contains($q, 'cara') || str_contains($q, 'aplikasi') || str_contains($q, 'profil') || $q === 'keluar'))) {
            return "🚪 **Panduan Cara Logout (Keluar) dari Akun LaundryKu:**\n\n" .
                "1. Buka menu **Profil** (`/profile`) atau klik avatar/nama akun Anda di navigasi pojok kanan atas.\n" .
                "2. Gulir ke bagian paling bawah pada halaman profil Anda.\n" .
                "3. Klik tombol **Keluar** (Logout).\n\n" .
                "Sesi akun Anda akan ditutup secara aman. Kapan pun ingin masuk kembali, cukup akses menu **Masuk / Daftar** (`/auth`) menggunakan nomor WhatsApp atau Email terdaftar via kode OTP tanpa perlu mengingat password!";
        }

        // 2e. Mode Akun (Customer, Mitra Toko, Kurir)
        if (str_contains($q, 'mode') || str_contains($q, 'role') || str_contains($q, 'ganti peran') || str_contains($q, 'multi role') || str_contains($q, 'pindah mode')) {
            return "👤 **Mode Pengguna & Peran Akun di LaundryKu:**\n\n" .
                "LaundryKu mendukung sistem multi-role dalam satu akun:\n" .
                "1. **Mode Customer:** Mode utama untuk semua pengguna mencari outlet di menu **Cari Toko** (`/stores`), membuat pesanan laundry, live tracking kurir, dan pembayaran.\n" .
                "2. **Mode Mitra Toko:** Akses ke **Dashboard Toko** bagi pemilik/pengelola toko untuk mengelola paket layanan, harga per kg, input timbangan riil, serta penugasan kurir toko.\n" .
                "3. **Mode Kurir:** Antarmuka khusus kurir untuk menerima tugas penjemputan (*pickup*) dan pengantaran (*delivery*), memperbarui status, dan konfirmasi serah terima cucian.\n\n" .
                "Pengguna yang telah terdaftar sebagai mitra toko atau kurir dapat beralih mode melalui menu profil akun.";
        }

        // 2f. Batas Waktu Pembayaran 1 Jam (Expired)
        if ((str_contains($q, 'bayar') || str_contains($q, 'pembayaran') || str_contains($q, 'qris')) &&
            (str_contains($q, 'batas') || str_contains($q, 'expired') || str_contains($q, 'kedaluwarsa') || str_contains($q, 'kadaluarsa') || str_contains($q, 'tenggat') || str_contains($q, 'berapa lama bayar') || str_contains($q, '1 jam') || str_contains($q, 'satu jam'))) {
            return "⏱️ **Batas Waktu Pembayaran Tagihan di LaundryKu:**\n\n" .
                "Batas waktu pembayaran untuk transaksi online (QRIS dan Virtual Account) adalah **1 jam (60 menit)** sejak rincian tagihan diterbitkan setelah penimbangan disetujui.\n\n" .
                "• **Jika pembayaran diselesaikan dalam 1 jam:** Status pesanan akan otomatis terkonfirmasi (*PAID*) dan cucian langsung masuk antrean cuci (*PROCESSING*).\n" .
                "• **Jika lewat dari 1 jam belum dibayar:** Tagihan otomatis kedaluwarsa (*EXPIRED_UNPAID*), pesanan dibatalkan, dan cucian akan masuk alur pengembalian oleh kurir ke alamat Anda.\n\n" .
                "Anda dapat memeriksa batas waktu dan menyelesaikan pembayaran di menu **Pesanan Saya** (`/orders`).";
        }

        // 2g. Kurir Sampai Jam Berapa / Perkiraan Waktu Tiba Kurir (Tanpa Menghasilkan Menit/Jam)
        if (str_contains($q, 'kurir') && (str_contains($q, 'jam berapa') || str_contains($q, 'kapan sampai') || str_contains($q, 'kapan tiba') || str_contains($q, 'sampai jam') || str_contains($q, 'eta') || (str_contains($q, 'sampai') && str_contains($q, 'kapan')))) {
            return "🛵 **Perkiraan Waktu Kedatangan Kurir LaundryKu:**\n\n" .
                "Waktu kedatangan kurir untuk penjemputan maupun pengantaran cucian bergantung pada rute perjalanan, kondisi lalu lintas di jalan, serta antrean penjemputan pada toko mitra terkait. Asisten platform tidak dapat memberikan taksiran jam kedatangan secara sepihak.\n\n" .
                "Untuk memantau kedatangan kurir secara akurat:\n" .
                "1. **Pantau Live Tracking:** Buka menu **Pesanan Saya** (`/orders`) untuk melihat status penugasan kurir (*PICKUP_ASSIGNED* atau *OUT_FOR_DELIVERY*) serta posisi kurir secara langsung pada peta pelacakan.\n" .
                "2. **Hubungi Kurir Langsung:** Anda juga dapat mengonfirmasi posisi atau patokan alamat Anda secara langsung melalui fitur **Chat Toko** (`/chat`).";
        }

        // 2h. Identitas Diri Pengguna ("siapa saya", "role saya", "mode aktif saya", "akun saya")
        if (preg_match('/\b(siapa|peran|role|profil|akun|mode\s+aktif)\s+(saya|aku|gue|ku)\b/i', $q) ||
            preg_match('/\b(saya|aku|gue)\s+(siapa|role\s+apa|peran\s+apa|sebagai\s+apa)\b/i', $q) ||
            $q === 'siapa saya' || $q === 'siapa aku' || $q === 'role saya' || $q === 'peran saya') {
            if (!$user) {
                return "🔒 **Informasi Akun Pribadi:**\n\n" .
                    "Anda saat ini belum masuk (masuk sebagai Tamu). Untuk mengetahui identitas, nama, role, dan mode aktif akun Anda, silakan masuk terlebih dahulu melalui menu **Masuk / Daftar** (`/auth`).";
            }

            $roles = $user->roles->pluck('code')->toArray();
            $rolesStr = !empty($roles) ? implode(', ', $roles) : 'customer';
            $activeMode = $user->active_mode ?: 'customer';
            $availableModes = method_exists($user, 'getAvailableModes') ? $user->getAvailableModes() : [$activeMode];
            $availableModesStr = implode(', ', $availableModes);

            $resp = "Halo **{$user->name}**!\n\nBerikut adalah informasi akun Anda di platform LaundryKu:\n" .
                "• **Nama:** {$user->name}\n" .
                "• **Role Terdaftar:** {$rolesStr}\n" .
                "• **Mode Aktif:** {$activeMode}\n" .
                "• **Mode Tersedia:** {$availableModesStr}\n";

            // If store owner / store admin
            $stores = Store::where('owner_user_id', $user->id)->where('approval_status', 'approved')->get();
            if ($stores->isEmpty() && in_array('store_admin', $roles, true)) {
                $storeIds = $user->roles()->whereNotNull('user_roles.store_id')->pluck('user_roles.store_id');
                if ($storeIds->isNotEmpty()) {
                    $stores = Store::whereIn('id', $storeIds)->where('approval_status', 'approved')->get();
                }
            }
            if ($stores->isNotEmpty()) {
                $storeNames = $stores->pluck('name')->implode(', ');
                $resp .= "• **Toko Mitra:** {$storeNames}\n";
            }

            // If courier
            $assignment = CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'active')
                ->with('store')
                ->first();
            if ($assignment && $assignment->store) {
                $resp .= "• **Penugasan Kurir:** Bertugas di {$assignment->store->name}\n";
            }

            $resp .= "\nAda hal seputar layanan atau akun LaundryKu yang bisa saya bantu hari ini?";
            return $resp;
        }

        // 2i. Toko Milik Pengguna ("saya punya toko apa", "toko saya", "toko yang saya miliki")
        if (preg_match('/\b(toko|outlet)\s+(saya|milik saya|ku)\b/i', $q) ||
            str_contains($q, 'saya punya toko') || str_contains($q, 'toko apa yang saya') ||
            str_contains($q, 'toko yang saya miliki') || str_contains($q, 'toko yang saya kelola') ||
            str_contains($q, 'nama toko saya')) {
            if (!$user) {
                return "🔒 **Informasi Toko Mitra:**\n\n" .
                    "Anda saat ini belum login. Silakan masuk terlebih dahulu melalui menu **Masuk / Daftar** (`/auth`) untuk memeriksa data toko milik akun Anda.";
            }

            $stores = Store::where('owner_user_id', $user->id)->where('approval_status', 'approved')->get();
            if ($stores->isEmpty() && $user->hasRole('store_admin')) {
                $storeIds = $user->roles()->whereNotNull('user_roles.store_id')->pluck('user_roles.store_id');
                if ($storeIds->isNotEmpty()) {
                    $stores = Store::whereIn('id', $storeIds)->where('approval_status', 'approved')->get();
                }
            }

            if ($stores->isNotEmpty()) {
                $resp = "Halo **{$user->name}**, Anda terdaftar mengelola toko laundry mitra berikut:\n\n";
                foreach ($stores as $st) {
                    $appr = strtoupper($st->approval_status ?? 'APPROVED');
                    $op = strtoupper($st->operational_status ?? 'ACTIVE');
                    $resp .= "🏪 **{$st->name}**\n";
                    $resp .= "• Alamat: {$st->address_text}\n";
                    $resp .= "• Status Approval: {$appr}\n";
                    $resp .= "• Operasional: {$op}\n\n";
                }
                $resp .= "Anda dapat mengelola layanan, harga per kg, timbangan riil, dan operasional toko melalui menu **Dashboard Toko** (`/store`).";
                return $resp;
            }

            return "Halo **{$user->name}**, akun Anda saat ini belum memiliki toko laundry mitra terdaftar. Jika Anda pemilik usaha laundry dan ingin bermitra, Anda dapat mendaftar melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pendaftaran Toko**.";
        }

        // 2j. Tugas Kurir Pengguna ("tugas saya apa", "apa tugas saya", "penugasan saya")
        if (preg_match('/\b(tugas|penugasan)\s+(saya|ku|kurir saya)\b/i', $q) ||
            str_contains($q, 'tugas saya') || str_contains($q, 'apa tugas saya') ||
            str_contains($q, 'penugasan saya') || str_contains($q, 'job saya')) {
            if (!$user) {
                return "🔒 **Informasi Penugasan Kurir:**\n\n" .
                    "Anda saat ini belum login. Silakan masuk terlebih dahulu melalui menu **Masuk / Daftar** (`/auth`) untuk melihat informasi tugas kurir Anda.";
            }

            $assignment = CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'active')
                ->with('store')
                ->first();

            if ($assignment && $assignment->store) {
                $tasks = Order::where('courier_id', $user->id)
                    ->whereIn('status', ['PICKUP_ASSIGNED', 'PICKED_UP', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY'])
                    ->with('store')
                    ->take(5)
                    ->get();

                $resp = "Halo **{$user->name}**! Anda aktif bertugas sebagai kurir untuk mitra toko **{$assignment->store->name}**.\n\n";
                if ($tasks->isNotEmpty()) {
                    $resp .= "📋 **Daftar Tugas Aktif Anda Saat Ini:**\n";
                    foreach ($tasks as $task) {
                        $taskType = in_array($task->status, ['PICKUP_ASSIGNED', 'PICKED_UP'], true) ? 'Penjemputan (Pickup)' : 'Pengantaran (Delivery)';
                        $resp .= "• **No: {$task->order_number}** ({$taskType}) - Status: {$this->translateStatus($task->status)}\n";
                    }
                    $resp .= "\nBuka menu **Tugas Kurir** (`/courier/tasks`) untuk melihat detail alamat penjemputan/pengantaran dan navigasi rute.";
                } else {
                    $resp .= "Saat ini Anda belum memiliki tugas penjemputan atau pengantaran yang sedang berjalan. Anda dapat memeriksa order baru yang siap diambil di menu **Tugas Kurir** (`/courier/tasks`).";
                }
                return $resp;
            }

            return "Halo **{$user->name}**, akun Anda saat ini belum terdaftar memiliki penugasan kurir aktif. Jika Anda ingin bergabung sebagai kurir mitra penjemputan/pengantaran LaundryKu, Anda dapat mendaftar melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pengajuan Kurir**.";
        }

        // 3. Multi-turn context awareness from history
        $priorText = '';
        if (!empty($history)) {
            $priorPieces = [];
            foreach (array_reverse($history) as $h) {
                $priorPieces[] = self::normalizeQuery($h['content'] ?? '');
            }
            $priorText = implode(' ', array_slice($priorPieces, 0, 4));
        }

        $isPriorAboutMitra = str_contains($priorText, 'mitra') || str_contains($priorText, 'toko');
        $isPriorAboutKurir = str_contains($priorText, 'kurir') || str_contains($priorText, 'antar');

        // Contextual follow-up: "Terus syaratnya apa?"
        if (str_contains($q, 'syarat') && (str_contains($q, 'apa') || strlen($q) < 25)) {
            if ($isPriorAboutKurir) {
                return "🛵 **Persyaratan Pendaftaran Kurir LaundryKu:**\n\n" .
                    "1. KTP & NIK valid (disimpan aman terenkripsi).\n" .
                    "2. SIM C aktif.\n" .
                    "3. Sepeda motor pribadi dengan plat nomor valid.\n" .
                    "4. Nomor WhatsApp aktif untuk penjemputan & pengantaran.\n\n" .
                    "Daftar melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pengajuan Kurir**.";
            }
            if ($isPriorAboutMitra) {
                return "🏪 **Persyaratan Pendaftaran Mitra Toko LaundryKu:**\n\n" .
                    "1. Nama outlet toko dan foto fisik outlet.\n" .
                    "2. Titik koordinat GPS & alamat lengkap (radius layanan maks. 10 km).\n" .
                    "3. Foto KTP pemilik dan izin usaha / NIB jika ada.\n" .
                    "4. Jam operasional dan kontak WhatsApp toko.\n\n" .
                    "Daftar melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pendaftaran Toko**.";
            }
            return "📋 **Persyaratan Pendaftaran di LaundryKu:**\n\n" .
                "• **Pelanggan:** Nama lengkap dan nomor WhatsApp aktif di menu `/auth`.\n" .
                "• **Mitra Toko:** KTP, foto outlet toko, GPS, dan kontak bisnis di `/upgrade`.\n" .
                "• **Kurir:** KTP valid, SIM C aktif, motor, dan nomor WA di `/upgrade`.\n\n" .
                "Bagian mana yang ingin Anda daftarkan?";
        }

        // Contextual follow-up: Verification time
        if ((str_contains($q, 'berapa lama') || str_contains($q, 'kapan')) &&
            (str_contains($q, 'verifikasi') || str_contains($q, 'disetujui') || str_contains($q, 'approval') || $isPriorAboutMitra || $isPriorAboutKurir)) {
            return "⏱️ **Waktu Verifikasi Akun Mitra & Kurir:**\n\n" .
                "Proses verifikasi dan validasi data pendaftaran mitra toko maupun kurir oleh tim Platform Admin diproses dalam waktu **1x24 jam kerja** setelah formulir dan dokumen dikirimkan lengkap.\n\n" .
                "Status persetujuan diperbarui langsung pada halaman akun Anda di menu **Gabung Mitra** (`/upgrade`).";
        }

        // 4. Pembatalan Pesanan ("Mau batalin pesanan", "Mau batalin", "Gimana cara batalin pesanan?")
        if (str_contains($q, 'batal') || str_contains($q, 'cancel')) {
            return "🛑 **Panduan Pembatalan Pesanan LaundryKu:**\n\n" .
                "1. **Tahap Konfirmasi Harga (*AWAITING_PRICE_APPROVAL*):**\n" .
                "   • Anda berhak membatalkan pesanan secara mandiri di menu **Pesanan Saya** (`/orders`) — **100% bebas biaya** jika tidak menyetujui hasil penimbangan toko.\n\n" .
                "2. **Tahap Sedang Diproses (*PROCESSING*):**\n" .
                "   • Jika cucian sudah dalam proses cuci fisik, pembatalan otomatis dinonaktifkan.\n" .
                "   • Segera hubungi pihak toko mitra via menu **Chat Toko** (`/chat`) untuk koordinasi penghentian pencucian.\n\n" .
                "3. **Tahap Pengantaran (*OUT_FOR_DELIVERY*):**\n" .
                "   • Pesanan tidak dapat dibatalkan karena proses cuci selesai dan sedang diantar kurir.\n\n" .
                "💡 *Apakah pesanan Anda saat ini masih menunggu penimbangan atau sudah mulai dicuci?*";
        }

        // 5. Kurir Belum Datang / Pengiriman Terlambat ("Kurir gue belum dateng", "Kurir blm dateng")
        if ((str_contains($q, 'kurir') && (str_contains($q, 'belum') || str_contains($q, 'datang') || str_contains($q, 'lama') || str_contains($q, 'jemput'))) ||
            str_contains($q, 'belum jemput')) {
            return "🛵 **Panduan Jika Kurir Belum Tiba di Lokasi Anda:**\n\n" .
                "1. **Pantau Status Real-Time di Menu Pesanan Saya (`/orders`):**\n" .
                "   • *PENDING_PICKUP*: Toko sedang mencari/menugaskan kurir terdekat.\n" .
                "   • *PICKUP_ASSIGNED*: Kurir telah menerima tugas dan sedang bergerak menuju alamat Anda.\n\n" .
                "2. **Hubungi Toko atau Kurir Langsung:**\n" .
                "   • Buka menu **Chat Toko** (`/chat`) untuk mengonfirmasi perkiraan waktu kedatangan kurir atau memberikan patokan alamat rumah Anda.\n\n" .
                "💡 *Apakah Anda sedang menunggu kurir untuk penjemputan cucian kotor atau pengantaran cucian bersih?*";
        }

        // 6. Customer Service & Kontak CS ("Cara hubungi CS?", "Cara hubungi CS gimana?")
        if (str_contains($q, 'hubungi cs') || str_contains($q, 'kontak cs') ||
            (str_contains($q, 'cs') && (str_contains($q, 'cara') || str_contains($q, 'gimana') || str_contains($q, 'nomor') || str_contains($q, 'bantuan'))) ||
            str_contains($q, 'customer service')) {
            return "📞 **Cara Menghubungi Customer Service (CS) & Bantuan LaundryKu:**\n\n" .
                "1. **Komunikasi Langsung dengan Toko & Kurir (Paling Cepat):**\n" .
                "   • Buka menu **Pesan & Chat Toko** (`/chat`) untuk berkirim pesan langsung dengan pihak outlet atau kurir yang menangani pesanan Anda.\n\n" .
                "2. **Pengaduan Resmi Kendala Pesanan:**\n" .
                "   • Buka menu **Pesanan Saya** (`/orders`) -> pilih pesanan terkait -> klik **Ajukan Komplain** untuk kendala cucian atau penagihan yang butuh bantuan admin platform.\n\n" .
                "3. **Customer Support Platform:**\n" .
                "   • Kunjungi menu **Profil** (`/profile`) untuk kontak resmi WhatsApp Customer Care (CS) LaundryKu.";
        }

        // 7. Komplain & Kerusakan Pakaian ("Gue mau komplain", "Baju gue rusak", "Luntur")
        if (str_contains($q, 'komplain') ||
            (str_contains($q, 'pakaian') && (str_contains($q, 'rusak') || str_contains($q, 'luntur') || str_contains($q, 'hilang') || str_contains($q, 'noda'))) ||
            (str_contains($q, 'rusak') && str_contains($q, 'cuci')) ||
            str_contains($q, 'tidak bersih')) {
            return "⚠️ **Panduan Pengajuan Komplain & Klaim Pakaian:**\n\n" .
                "1. **Ambil Foto Bukti:** Foto kondisi pakaian yang bermasalah secara jelas (sobek, luntur, noda tertinggal, atau kancing lepas).\n" .
                "2. **Ajukan Komplain Resmi:** Buka menu **Pesanan Saya** (`/orders`) -> pilih pesanan -> klik tombol **Ajukan Komplain** (maksimal **1x24 jam** setelah pesanan berstatus selesai/*COMPLETED*).\n" .
                "3. **Penyelesaian Garansi:** Admin toko mitra dan platform akan meninjau laporan untuk solusi pencucian ulang bebas biaya atau kompensasi ganti rugi.\n" .
                "4. **Diskusi Langsung:** Anda juga dapat mengirim foto dan berdiskusi langsung dengan kasir toko melalui menu **Chat Toko** (`/chat`).\n\n" .
                "💡 *Boleh tahu apakah kendala Anda terkait pakaian rusak/luntur, barang tertukar, atau hasil cucian yang kurang bersih?*";
        }

        // 8. Pengembalian Dana / Refund ("Uang gue balik ga?", "Refund")
        if (str_contains($q, 'refund') ||
            (str_contains($q, 'uang') && (str_contains($q, 'balik') || str_contains($q, 'kembali') || str_contains($q, 'hangus')))) {
            return "💰 **Ketentuan & Prosedur Pengembalian Dana (Refund) di LaundryKu:**\n\n" .
                "1. **Pembatalan di Tahap Penolakan Harga:** Jika Anda membatalkan pesanan di tahap persetujuan harga timbangan (*AWAITING_PRICE_APPROVAL*) atau sebelum pembayaran diproses, tidak ada dana yang terpotong.\n" .
                "2. **Pesanan yang Sudah Dibayar Dibatalkan / Komplain Disetujui:** Dana Anda berhak dikembalikan (refund) jika pesanan dibatalkan oleh pihak toko atau pengajuan komplain Anda disetujui dalam mediasi platform.\n" .
                "3. **Alur & Estimasi Refund:** Pengembalian dana diproses via payment gateway Xendit ke rekening bank atau saldo e-wallet asal dalam waktu 1-3 hari kerja.\n\n" .
                "⚠️ *Catatan: Asisten AI tidak dapat menyatakan transaksi refund telah berhasil tanpa verifikasi data sistem. Anda dapat memantau status di menu Pesanan Saya (`/orders`) atau hubungi toko via Chat Toko.*";
        }

        // 9. Pengerjaan Laundry Terasa Lama ("Laundry gue udah lama banget", "Laundry gue belum selesai")
        if ((str_contains($q, 'laundry') && (preg_match('/\blama\b/', $q) || str_contains($q, 'belum selesai') || str_contains($q, 'kapan selesai'))) ||
            (str_contains($q, 'pesanan') && preg_match('/\blama\b/', $q))) {
            return "⏱️ **Panduan Jika Pengerjaan Laundry Terasa Lama:**\n\n" .
                "1. **Cek Estimasi Paket Layanan:**\n" .
                "   • Layanan Regular Kiloan / Satuan umumnya memakan waktu 1-3 hari kerja sesuai antrean toko.\n" .
                "   • Layanan Express diproses prioritas selesai dalam 6-24 jam.\n" .
                "2. **Pantau Tahapan Pengerjaan di Menu Pesanan Saya (`/orders`):**\n" .
                "   • Lihat apakah pakaian sedang dalam proses cuci (*WASHING*), pengeringan (*DRYING*), penyetrikaan (*IRONING*), atau sudah siap diantar (*READY_FOR_DELIVERY*).\n" .
                "3. **Hubungi Toko Langsung:** Tanyakan langsung antrean pakaian Anda ke staf toko via menu **Chat Toko** (`/chat`).\n\n" .
                "💡 *Boleh tahu apakah pesanan Anda menggunakan paket Reguler atau Express?*";
        }

        // 10. Pembayaran Bermasalah / Gagal ("Pembayaran gue bermasalah", "Pembayaran gue gagal")
        if ((str_contains($q, 'pembayaran') || str_contains($q, 'bayar')) &&
            (str_contains($q, 'bermasalah') || str_contains($q, 'gagal') || str_contains($q, 'error') || str_contains($q, 'tidak bisa'))) {
            return "💳 **Solusi Kendala Pembayaran di LaundryKu:**\n\n" .
                "1. **Pembayaran Gagal atau Waktu Habis (Expired):**\n" .
                "   • Buka menu **Pesanan Saya** (`/orders`), pilih pesanan, lalu klik tombol **Bayar Sekarang** untuk membuat kode QRIS atau nomor Virtual Account baru.\n\n" .
                "2. **Saldo Terpotong tapi Status Masih Belum Berubah:**\n" .
                "   • Sistem payment gateway Xendit butuh waktu 1-5 menit untuk sinkronisasi webhook otomatis. Segarkan (*refresh*) halaman `/orders`.\n" .
                "   • Jika lewat 15 menit belum berubah, jangan bayar ulang; kirimkan foto bukti transfer ke admin toko melalui menu **Chat Toko** (`/chat`).\n\n" .
                "3. **Opsi Tunai (COD):** Anda juga dapat memilih metode Cash on Delivery (bayar tunai ke kurir saat pengantaran).";
        }

        // 11. Pembayaran Pending / Sudah Bayar tapi Belum Berubah
        if ((str_contains($q, 'bayar') || str_contains($q, 'pembayaran')) &&
            (str_contains($q, 'pending') || str_contains($q, 'belum masuk') || str_contains($q, 'belum berubah') || str_contains($q, 'sudah transfer'))) {
            return "💳 **Panduan Kendala Status Pembayaran Masih Pending:**\n\n" .
                "Jika Anda sudah menyelesaikan pembayaran namun status pesanan di sistem belum berubah menjadi *PAID*:\n\n" .
                "1. **Tunggu Sinkronisasi:** Sistem payment gateway (QRIS / Virtual Account) membutuhkan waktu 1-5 menit untuk sinkronisasi konfirmasi ke sistem LaundryKu.\n" .
                "2. **Muat Ulang Halaman:** Buka kembali menu **Pesanan Saya** (`/orders`) dan refresh halaman untuk memperbarui status terbaru.\n" .
                "3. **Periksa Mutasi Rekening:** Pastikan saldo rekening atau e-wallet Anda memang sudah terpotong.\n" .
                "4. **Konfirmasi Bukti Transfer:** Jika setelah 15 menit status masih belum berubah, hubungi admin toko via menu **Pesan Chat** (`/chat`) dengan melampirkan nomor pesanan dan foto bukti transfer.";
        }

        // 12. Ubah Alamat / Data Diri / Profil ("Bisa ubah alamat nggak?", "Isi data diri", "Alamat pesanan salah")
        if ((str_contains($q, 'alamat') &&
            (str_contains($q, 'ubah') || str_contains($q, 'ganti') || str_contains($q, 'salah') || str_contains($q, 'pindah'))) ||
            str_contains($q, 'data diri') || str_contains($q, 'isi data')) {
            return "📍 **Panduan Perubahan & Kendala Alamat Penjemputan / Pengantaran:**\n\n" .
                "1. **Pesanan yang Sedang Berjalan:**\n" .
                "   • Jika pesanan sudah dibuat dan kurir belum berangkat, segera buka menu **Chat Toko** (`/chat`) dan informasikan patokan alamat yang benar langsung ke pihak toko atau kurir (selama masih dalam radius 10 km dari toko).\n\n" .
                "2. **Ubah Alamat Akun untuk Pesanan Berikutnya:**\n" .
                "   • Anda dapat memperbarui detail jalan, patokan, dan titik koordinat GPS alamat utama Anda di menu **Profil & Alamat** (`/profile`).\n\n" .
                "💡 *Apakah kurir saat ini sedang menuju lokasi Anda untuk menjemput atau mengantar cucian?*";
        }

        // 12b. Penimbangan Cucian
        if (str_contains($q, 'timbang') || str_contains($q, 'penimbangan')) {
            return "⚖️ **Alur Penimbangan Cucian di LaundryKu:**\n\n" .
                "1. Kurir menjemput cucian dan mengantarkannya ke outlet toko mitra.\n" .
                "2. Pihak toko melakukan **Penimbangan** cucian secara akurat di outlet.\n" .
                "3. Hasil timbangan dan total harga akan dikirim ke aplikasi untuk persetujuan Anda di menu **Pesanan Saya** (`/orders`).";
        }

        // 12c. Pembayaran & Metode Bayar
        if (str_contains($q, 'bayar') || str_contains($q, 'pembayaran')) {
            return "💳 **Metode Pembayaran Resmi di LaundryKu:**\n\n" .
                "1. **QRIS Instan:** Scan QR code melalui GoPay, OVO, DANA, ShopeePay, LinkAja, BCA Mobile, Livin Mandiri, dan semua m-Banking berstandar QRIS.\n" .
                "2. **Virtual Account (VA Bank):** Transfer otomatis via bank BCA, Mandiri, BNI, BRI, atau Permata via payment gateway Xendit dengan konfirmasi instan.\n" .
                "3. **COD (Cash on Delivery):** Bayar tunai langsung kepada kurir saat cucian diantar kembali ke lokasi Anda tanpa biaya tambahan.";
        }

        // 13. Status Pesanan Belum Berubah / Stuck ("Pesanan gue belum berubah status")
        if (str_contains($q, 'status') &&
            (str_contains($q, 'belum berubah') || str_contains($q, 'tidak berubah') || str_contains($q, 'stuck') || str_contains($q, 'sama saja'))) {
            return "🔄 **Penjelasan Mengapa Status Pesanan Belum Berubah:**\n\n" .
                "1. **Pembaruan Manual di Toko:** Perubahan status (seperti dari *PICKED_UP* ke *AT_STORE*, *WEIGHED*, lalu *PROCESSING*) diperbarui oleh staf toko sesuai antrean pengerjaan fisik cucian di outlet.\n" .
                "2. **Antrean Penimbangan & Cuci:** Jika status masih di tahap outlet (*AT_STORE*), pakaian Anda kemungkinan sedang dalam antrean timbang atau pemilahan bahan kain.\n" .
                "3. **Konfirmasi Langsung:** Jika status tidak berubah melebihi batas waktu wajar, kirim pesan ke kasir toko melalui menu **Chat Toko** (`/chat`) untuk memastikan progres antrean pakaian Anda.";
        }

        // 14. Pendaftaran Mitra Toko ("Gimana cara daftar jadi mitra?", "Gimana cara daftar mitra?")
        if (str_contains($q, 'daftar mitra') || str_contains($q, 'jadi mitra') || str_contains($q, 'gabung mitra') ||
            (str_contains($q, 'daftar') && str_contains($q, 'toko')) ||
            (str_contains($q, 'buka') && str_contains($q, 'mitra')) ||
            (str_contains($q, 'buka toko') && !str_contains($q, 'jam') && !str_contains($q, 'hari') && !str_contains($q, 'pas') && !str_contains($q, 'ga') && !str_contains($q, 'tidak'))) {
            return "🏪 **Panduan & Syarat Menjadi Mitra Toko LaundryKu:**\n\n" .
                "**1. Cara Mendaftar:**\n" .
                "• Buka menu **Gabung Mitra** (`/upgrade`) lalu pilih tab **Pendaftaran Toko**.\n" .
                "• Isi data toko: Nama Outlet, Alamat Lengkap, Titik Koordinat GPS (radius jangkauan maks 10 km), Jam Operasional, dan Kontak WhatsApp.\n" .
                "• Unggah dokumen pendukung: Foto KTP Pemilik, Foto Fisik Outlet Toko, dan Dokumen NIB/Izin Usaha (jika ada).\n\n" .
                "**2. Waktu Verifikasi:**\n" .
                "• Tim Platform Admin memvalidasi data toko dalam waktu **1x24 jam kerja**.\n\n" .
                "**3. Setelah Disetujui:**\n" .
                "• Anda dapat mengakses **Dashboard Toko** untuk mengatur paket layanan, harga per kg, minimal order, menerima order baru, input timbangan riil, dan menugaskan kurir toko.";
        }

        // 15. Pendaftaran Kurir ("Mau jadi kurir", "Cara jadi kurir gimana?")
        if ((str_contains($q, 'daftar') || str_contains($q, 'jadi') || str_contains($q, 'mau')) &&
            str_contains($q, 'kurir')) {
            return "🛵 **Panduan & Syarat Bergabung Sebagai Kurir LaundryKu:**\n\n" .
                "**1. Persyaratan yang Diperlukan:**\n" .
                "• KTP & NIK valid.\n" .
                "• SIM C aktif.\n" .
                "• Sepeda motor pribadi dengan plat nomor kendaraan yang valid.\n" .
                "• Nomor WhatsApp aktif untuk koordinasi penjemputan/pengantaran.\n\n" .
                "**2. Cara Mendaftar:**\n" .
                "• Buka menu **Gabung Mitra** (`/upgrade`) -> pilih tab **Pengajuan Kurir**.\n" .
                "• Lengkapi formulir identitas, data kendaraan, dan unggah foto SIM C serta KTP.\n" .
                "• Verifikasi diproses dalam waktu **1x24 jam kerja** oleh admin toko atau platform.";
        }

        // 16. Metode Pembayaran Resmi yang Didukung
        if (str_contains($q, 'metode pembayaran') || str_contains($q, 'cara bayar') ||
            str_contains($q, 'bayar pakai') || str_contains($q, 'bisa bayar') ||
            str_contains($q, 'qris') || str_contains($q, 'virtual account') || preg_match('/\bcod\b/i', $q)) {
            return "💳 **Metode Pembayaran Resmi di LaundryKu:**\n\n" .
                "1. **QRIS Instan:** Scan QR code melalui GoPay, OVO, DANA, ShopeePay, LinkAja, BCA Mobile, Livin Mandiri, dan semua m-Banking berstandar QRIS.\n" .
                "2. **Virtual Account (VA Bank):** Transfer otomatis via bank BCA, Mandiri, BNI, BRI, atau Permata via payment gateway Xendit dengan konfirmasi instan.\n" .
                "3. **COD (Cash on Delivery):** Bayar tunai langsung kepada kurir saat cucian diantar kembali ke lokasi Anda tanpa biaya tambahan.";
        }

        // 16b. Informasi Harga & Tarif Layanan (Cuci Kiloan, Satuan, Express)
        if (str_contains($q, 'harga') || str_contains($q, 'tarif') || str_contains($q, 'biaya cuci') ||
            str_contains($q, 'kiloan') || str_contains($q, 'per kilo') || str_contains($q, 'perkilo') ||
            str_contains($q, 'berapa harga') || str_contains($q, 'ongkos cuci')) {
            return "🏷️ **Informasi Harga & Tarif Layanan LaundryKu:**\n\n" .
                "Setiap outlet mitra toko laundry di LaundryKu menetapkan tarif layanannya secara transparan yang dapat dilihat langsung pada profil masing-masing toko:\n\n" .
                "• **Cuci Kiloan (Reguler):** Umumnya berkisar antara **Rp 6.000 – Rp 10.000 / kg** (waktu pengerjaan 2–3 hari kerja).\n" .
                "• **Cuci Kiloan (Express):** Berkisar antara **Rp 12.000 – Rp 18.000 / kg** (selesai dalam 6–24 jam prioritas).\n" .
                "• **Cuci Satuan / Dry Clean:** Dihitung per helai pakaian (misal: jas, selimut, bedcover, gamis) sesuai rincian pada toko.\n" .
                "• **Transparansi Penimbangan:** Biaya awal saat checkout adalah estimasi. Total harga final yang wajib dibayar dihitung berdasarkan **timbangan riil** saat cucian tiba di toko dan wajib disetujui di menu Pesanan Saya (`/orders`).\n\n" .
                "Untuk melihat daftar harga lengkap toko terdekat di sekitar Anda, buka menu **Cari Toko** (`/stores`).";
        }

        // 17. Cara Pesan Laundry & Estimasi Biaya
        if (str_contains($q, 'cara pesan') || str_contains($q, 'cara memesan') ||
            str_contains($q, 'buat pesanan') || str_contains($q, 'alur pemesanan') ||
            str_contains($q, 'estimasi biaya') || str_contains($q, 'timbangan')) {
            return "📋 **Cara Membuat Pesanan & Memahami Estimasi Biaya di LaundryKu:**\n\n" .
                "1. **Cari Toko:** Buka menu **Cari Toko** (`/stores`) dan pilih outlet laundry terdekat dalam radius 10 km.\n" .
                "2. **Pilih Layanan & Estimasi:** Masukkan estimasi berat (untuk kiloan) atau jumlah helai (untuk satuan).\n" .
                "3. **Penjemputan:** Tentukan alamat penjemputan. Kurir toko akan datang mengambil pakaian Anda.\n" .
                "4. **Penimbangan Riil & Persetujuan:** Saat tiba di toko, admin menimbang cucian dan menetapkan **Harga Final**. Anda menerima konfirmasi persetujuan harga di menu **Pesanan Saya** (`/orders`). Anda berhak menyetujui atau membatalkan pesanan bebas biaya!\n" .
                "5. **Pembayaran & Pengantaran:** Bayar via QRIS, Virtual Account, atau tunai COD saat cucian bersih diantar.";
        }

        // 18. Pendaftaran Akun Pelanggan (Customer)
        if (str_contains($q, 'daftar akun') || str_contains($q, 'buat akun') ||
            str_contains($q, 'registrasi') || str_contains($q, 'bikin akun') ||
            str_contains($q, 'cara daftar') ||
            (str_contains($q, 'daftar') && !str_contains($q, 'mitra') && !str_contains($q, 'kurir'))) {
            return "📱 **Panduan Mendaftar Akun Customer di LaundryKu:**\n\n" .
                "1. Klik menu **Masuk / Daftar** (`/auth`) di navbar pojok kanan atas.\n" .
                "2. Pilih tab **Daftar Akun Baru**.\n" .
                "3. Masukkan **Nama Lengkap** dan **Nomor WhatsApp** aktif (atau Email).\n" .
                "4. Klik tombol **Daftar & Dapatkan OTP**.\n" .
                "5. Masukkan 6 digit kode OTP yang diterima. Akun Anda langsung aktif dengan sistem **Passwordless OTP** tanpa perlu mengingat password!";
        }

        // 19. Perbedaan Status Pesanan
        if ((str_contains($q, 'beda') && str_contains($q, 'status')) ||
            (str_contains($q, 'arti') && str_contains($q, 'status')) ||
            (str_contains($q, 'proses') && str_contains($q, 'siap diantar'))) {
            return "🔄 **Perbedaan Status Pengerjaan Pesanan:**\n\n" .
                "• **Diproses (`PROCESSING`):** Cucian Anda sedang dalam penanganan aktif oleh tim toko (tahap pencucian, pengeringan, atau setrika/pelipatan rapi).\n" .
                "• **Siap Diantar (`READY_FOR_DELIVERY`):** Proses pencucian dan pengepakan selesai 100%. Pakaian sudah bersih, harum, dan dipacking rapi menunggu kurir mengambilnya di outlet.\n" .
                "• **Sedang Diantar (`OUT_FOR_DELIVERY`):** Kurir sudah membawa paket pakaian bersih Anda dan sedang dalam perjalanan menuju alamat pengantaran Anda.";
        }

        // 20. Melacak Status Pesanan
        if (str_contains($q, 'lacak pesanan') || str_contains($q, 'melacak pesanan') ||
            str_contains($q, 'cara melacak') || str_contains($q, 'cara lacak') ||
            str_contains($q, 'lacak') || str_contains($q, 'melacak') || str_contains($q, 'tracking') ||
            str_contains($q, 'cek pesanan') || str_contains($q, 'status pesanan') ||
            str_contains($q, 'pesanan saya') || str_contains($q, 'cucian saya')) {
            if (!$user) {
                return "🔍 **Cara Melacak Status Pesanan Cucian Anda:**\n\n" .
                    "• **Untuk Pengguna Tamu (Belum Login):** Jika Anda saat ini belum login, silakan masuk terlebih dahulu melalui menu **Masuk / Daftar** (`/auth`). Demi privasi dan keamanan akun, asisten AI tidak dapat mengakses riwayat transaksi pribadi tanpa autentikasi yang sah.\n\n" .
                    "• **Cek Melalui Menu Pesanan Saya (`/orders`):** Setelah masuk ke akun, Anda dapat melihat rincian progres cucian secara langsung (tahap pickup, penimbangan, proses cuci, hingga pengantaran kurir).\n\n" .
                    "• **Hubungi Toko Langsung:** Anda juga dapat menggunakan fitur **Chat Toko** (`/chat`) untuk berdiskusi langsung dengan kasir toko.";
            }

            $orders = Order::where('customer_id', $user->id)
                ->with(['store', 'items'])
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get();

            if ($orders->isEmpty()) {
                return "Halo **{$user->name}**! Anda saat ini belum memiliki pesanan aktif di LaundryKu. Anda dapat membuka menu **Cari Toko** (`/stores`) untuk memesan jasa laundry terdekat.";
            }

            $resp = "Halo **{$user->name}**, berikut adalah status pesanan terbaru Anda:\n\n";
            foreach ($orders as $order) {
                $statusLabel = $this->translateStatus($order->status);
                $storeName = $order->store->name ?? 'Toko Laundry';
                $resp .= "🧺 **No. Pesanan: {$order->order_number}** ({$storeName})\n";
                $resp .= "• **Status:** {$statusLabel}\n";
                $resp .= "• **Pembayaran:** " . strtoupper($order->payment_status ?? 'UNPAID') . "\n";
                if ($order->actual_weight_kg) {
                    $resp .= "• **Berat Timbangan:** {$order->actual_weight_kg} kg (Harga Final: Rp " . number_format($order->final_amount ?? 0, 0, ',', '.') . ")\n";
                } else {
                    $resp .= "• **Estimasi:** Rp " . number_format($order->estimated_amount ?? 0, 0, ',', '.') . " ({$order->estimated_weight_kg} kg)\n";
                }
                $resp .= "\n";
            }
            $resp .= "💡 *Untuk melihat rincian lengkap atau menyetujui harga timbangan, buka menu **Pesanan Saya** (`/orders`).*";
            return $resp;
        }

        // 21. Out-of-Scope Off-Topic Rejection
        if (preg_match('/(presiden|politik|pemilu|resep|masak|nasi\s+goreng|cuaca|saham|crypto|judi|slot|game|film|selebriti|chord|gitar|fisika|matematika|ngoding|coding|code|javascript|python|php|react)/i', $q)) {
            return "Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?";
        }

        // 22. Pertanyaan Spesifik yang Belum Diketahui Sistem (Threshold Relevansi)
        $isLaundryRelevant = (
            str_contains($q, 'laundry') || str_contains($q, 'cucian') || str_contains($q, 'cuci') ||
            str_contains($q, 'pesan') || str_contains($q, 'order') || str_contains($q, 'toko') ||
            str_contains($q, 'outlet') || str_contains($q, 'kurir') || str_contains($q, 'batal') ||
            str_contains($q, 'bayar') || str_contains($q, 'harga') || str_contains($q, 'tarif') ||
            str_contains($q, 'buka') || str_contains($q, 'tutup') || str_contains($q, 'operasional') ||
            str_contains($q, 'jam') || str_contains($q, 'libur')
        );

        if ($isLaundryRelevant) {
            return "Informasi spesifik tersebut tergantung pada outlet toko laundry mitra yang bersangkutan. Di platform LaundryKu, setiap toko memiliki jam operasional, kebijakan hari libur, dan tarif layanan masing-masing yang dapat Anda cek secara langsung pada kartu profil toko di menu **Cari Toko** (`/stores`).\n\n" .
                "Anda juga dapat menanyakan hal tersebut langsung ke pihak toko melalui fitur **Chat Toko** (`/chat`). Ada hal lain seputar layanan LaundryKu yang bisa saya bantu jelaskan?";
        }

        return "Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?";
    }

    /**
     * Helper to translate operational status codes into user-friendly Indonesian labels.
     */
    protected function translateStatus(?string $status): string
    {
        return match ($status) {
            'PENDING_PICKUP' => 'Menunggu Penjemputan Kurir',
            'PICKUP_ASSIGNED' => 'Kurir Menuju Lokasi Jemput',
            'PICKED_UP' => 'Cucian Telah Dijemput Kurir',
            'AT_STORE' => 'Cucian Tiba di Outlet Toko',
            'WEIGHED' => 'Cucian Selesai Ditimbang',
            'AWAITING_PRICE_APPROVAL' => 'Menunggu Persetujuan Harga Final oleh Anda',
            'PRICE_APPROVED' => 'Harga Final Disetujui',
            'AWAITING_PAYMENT' => 'Menunggu Pembayaran',
            'PAID' => 'Pembayaran Terkonfirmasi',
            'PROCESSING' => 'Cucian Sedang Dicuci & Diproses',
            'READY_FOR_DELIVERY' => 'Cucian Bersih & Siap Diantar',
            'DELIVERY_ASSIGNED' => 'Kurir Pengantaran Ditugaskan',
            'OUT_FOR_DELIVERY' => 'Kurir Sedang Mengantar Cucian',
            'COMPLETED' => 'Pesanan Selesai',
            'CANCELLED' => 'Pesanan Dibatalkan',
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'PAYMENT_EXPIRED' => 'Pembayaran Kedaluwarsa',
            'EXPIRED_UNPAID' => 'Pembayaran Kedaluwarsa (1 Jam Tidak Dibayar)',
            default => $status ?? 'Diproses',
        };
    }
}
