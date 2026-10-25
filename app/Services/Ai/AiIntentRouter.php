<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiIntentRouter
{
    // Closed Intent Taxonomy
    public const INTENT_ORDER_PROGRESS = 'order_progress';
    public const INTENT_ORDER_WASHING_STAGE = 'order_washing_stage';
    public const INTENT_ORDER_WEIGHT = 'order_weight';
    public const INTENT_ORDER_PAYMENT_STATUS = 'order_payment_status';
    public const INTENT_CUSTOMER_ORDER_COUNT = 'customer_order_count';
    public const INTENT_CUSTOMER_SPENDING = 'customer_spending';
    public const INTENT_CUSTOMER_LAST_ORDER = 'customer_last_order';
    public const INTENT_SERVICES_AND_PRICES = 'services_and_prices';
    public const INTENT_BUSINESS_RULES = 'business_rules';
    public const INTENT_STORE_INFO = 'store_info';
    public const INTENT_COMPLAINT_OR_REVIEW = 'complaint_or_review';
    public const INTENT_ACCOUNT_INFO = 'account_info';
    public const INTENT_COURIER_TASKS = 'courier_tasks';
    public const INTENT_STORE_ORDERS = 'store_orders';
    public const INTENT_STORE_STATS = 'store_stats';
    public const INTENT_SUPER_ADMIN_PLATFORM = 'super_admin_platform';
    public const INTENT_BOT_IDENTITY = 'bot_identity';
    public const INTENT_PROCEDURAL_GUIDE = 'procedural_guide';
    public const INTENT_OUT_OF_SCOPE = 'out_of_scope';
    public const INTENT_UNKNOWN = 'unknown';

    // New Procedural Guides per Topic & Role
    public const INTENT_GUIDE_ORDER = 'guide_order';
    public const INTENT_GUIDE_PAYMENT = 'guide_payment';
    public const INTENT_GUIDE_TRACKING = 'guide_tracking';
    public const INTENT_GUIDE_CANCEL = 'guide_cancel';
    public const INTENT_GUIDE_COMPLAINT = 'guide_complaint';
    public const INTENT_GUIDE_REVIEW = 'guide_review';
    public const INTENT_GUIDE_ADDRESS = 'guide_address';
    public const INTENT_GUIDE_UPGRADE = 'guide_upgrade';
    public const INTENT_GUIDE_COURIER_TASKS = 'guide_courier_tasks';
    public const INTENT_GUIDE_COURIER_COD = 'guide_courier_cod';
    public const INTENT_GUIDE_COURIER_AVAILABILITY = 'guide_courier_availability';
    public const INTENT_GUIDE_STORE_ORDERS = 'guide_store_orders';
    public const INTENT_GUIDE_STORE_WEIGHING = 'guide_store_weighing';
    public const INTENT_GUIDE_STORE_SERVICES = 'guide_store_services';
    public const INTENT_GUIDE_STORE_HOURS = 'guide_store_hours';
    public const INTENT_GUIDE_STORE_COMPLAINTS = 'guide_store_complaints';
    public const INTENT_GUIDE_ADMIN_APPROVALS = 'guide_admin_approvals';
    public const INTENT_GUIDE_ADMIN_OVERVIEW = 'guide_admin_overview';
    public const INTENT_GUIDE_TOPICS_MENU = 'guide_topics_menu';

    // Store Owner/Admin Data Capabilities
    public const INTENT_STORE_SERVICES_DATA = 'store_services_data';
    public const INTENT_STORE_COMPLAINTS_DATA = 'store_complaints_data';
    public const INTENT_STORE_HOURS_DATA = 'store_hours_data';
    public const INTENT_STORE_COURIERS_DATA = 'store_couriers_data';
    public const INTENT_STORE_APPLICANTS_DATA = 'store_applicants_data';

    // Courier Data Capabilities
    public const INTENT_COURIER_STORE_INFO = 'courier_store_info';

    // Super Admin Data Capabilities
    public const INTENT_SUPER_ADMIN_SEARCH = 'super_admin_search';

    // Deterministic Greetings, Thanks, and Goodbye
    public const INTENT_GREETING = 'greeting';
    public const INTENT_THANKS = 'thanks';
    public const INTENT_GOODBYE = 'goodbye';

    public const ALL_INTENTS = [
        self::INTENT_ORDER_PROGRESS,
        self::INTENT_ORDER_WASHING_STAGE,
        self::INTENT_ORDER_WEIGHT,
        self::INTENT_ORDER_PAYMENT_STATUS,
        self::INTENT_CUSTOMER_ORDER_COUNT,
        self::INTENT_CUSTOMER_SPENDING,
        self::INTENT_CUSTOMER_LAST_ORDER,
        self::INTENT_SERVICES_AND_PRICES,
        self::INTENT_BUSINESS_RULES,
        self::INTENT_STORE_INFO,
        self::INTENT_COMPLAINT_OR_REVIEW,
        self::INTENT_ACCOUNT_INFO,
        self::INTENT_COURIER_TASKS,
        self::INTENT_STORE_ORDERS,
        self::INTENT_STORE_STATS,
        self::INTENT_SUPER_ADMIN_PLATFORM,
        self::INTENT_BOT_IDENTITY,
        self::INTENT_PROCEDURAL_GUIDE,
        self::INTENT_OUT_OF_SCOPE,
        self::INTENT_UNKNOWN,

        self::INTENT_GUIDE_ORDER,
        self::INTENT_GUIDE_PAYMENT,
        self::INTENT_GUIDE_TRACKING,
        self::INTENT_GUIDE_CANCEL,
        self::INTENT_GUIDE_COMPLAINT,
        self::INTENT_GUIDE_REVIEW,
        self::INTENT_GUIDE_ADDRESS,
        self::INTENT_GUIDE_UPGRADE,
        self::INTENT_GUIDE_COURIER_TASKS,
        self::INTENT_GUIDE_COURIER_COD,
        self::INTENT_GUIDE_COURIER_AVAILABILITY,
        self::INTENT_GUIDE_STORE_ORDERS,
        self::INTENT_GUIDE_STORE_WEIGHING,
        self::INTENT_GUIDE_STORE_SERVICES,
        self::INTENT_GUIDE_STORE_HOURS,
        self::INTENT_GUIDE_STORE_COMPLAINTS,
        self::INTENT_GUIDE_ADMIN_APPROVALS,
        self::INTENT_GUIDE_ADMIN_OVERVIEW,
        self::INTENT_GUIDE_TOPICS_MENU,

        self::INTENT_STORE_SERVICES_DATA,
        self::INTENT_STORE_COMPLAINTS_DATA,
        self::INTENT_STORE_HOURS_DATA,
        self::INTENT_STORE_COURIERS_DATA,
        self::INTENT_STORE_APPLICANTS_DATA,
        self::INTENT_COURIER_STORE_INFO,
        self::INTENT_SUPER_ADMIN_SEARCH,

        self::INTENT_GREETING,
        self::INTENT_THANKS,
        self::INTENT_GOODBYE,
    ];

    protected AiTextNormalizer $normalizer;
    protected ?AiClientInterface $client;
    protected AiKnowledgeBaseService $knowledgeBase;

    public function __construct(
        AiTextNormalizer $normalizer,
        ?AiClientInterface $client = null,
        ?AiKnowledgeBaseService $knowledgeBase = null
    ) {
        $this->normalizer = $normalizer;
        $this->client = $client;
        $this->knowledgeBase = $knowledgeBase ?? new AiKnowledgeBaseService();
    }

    public function getKnowledgeBase(): AiKnowledgeBaseService
    {
        return $this->knowledgeBase;
    }

    public function getNormalizer(): AiTextNormalizer
    {
        return $this->normalizer;
    }

    /**
     * Route user message through 3 layers:
     * Layer 1: Rule-based Scoring System
     * Layer 2: Model Router Fallback
     * Layer 3: Safe Clarification
     *
     * @return array{
     *     intent: string,
     *     layer: string,
     *     order_number: ?string,
     *     confidence: float,
     *     normalized_message: string,
     *     multi_intents: array<string>,
     *     context: array
     * }
     */
    public function route(User $user, string $rawMessage, array $conversationContext = []): array
    {
        $normalized = $this->normalizer->normalize($rawMessage);
        $orderNumber = $this->normalizer->extractOrderNumber($rawMessage);

        // Check for compound intents (e.g. "siapa kamu dan pesanan saya sampai mana")
        $multiIntents = $this->detectMultiIntents($normalized);

        // --- LAYER 1: Rule-Based Scoring ---
        $layer1Result = $this->evaluateLayer1($user, $normalized, $orderNumber, $conversationContext);

        if ($layer1Result['matched']) {
            return [
                'intent' => $layer1Result['intent'],
                'layer' => 'layer_1_rules',
                'order_number' => $layer1Result['order_number'] ?? $orderNumber,
                'confidence' => $layer1Result['confidence'],
                'normalized_message' => $normalized,
                'multi_intents' => $multiIntents,
                'context' => $conversationContext,
            ];
        }

        // --- LAYER 2: Model Router (Fallback) ---
        $layer2Result = $this->evaluateLayer2($user, $normalized, $orderNumber);
        if ($layer2Result['intent'] !== self::INTENT_UNKNOWN && in_array($layer2Result['intent'], self::ALL_INTENTS, true)) {
            return [
                'intent' => $layer2Result['intent'],
                'layer' => 'layer_2_router',
                'order_number' => $layer2Result['order_number'] ?? $orderNumber,
                'confidence' => $layer2Result['confidence'] ?? 0.85,
                'normalized_message' => $normalized,
                'multi_intents' => $multiIntents,
                'context' => $conversationContext,
            ];
        }

        // --- LAYER 3: Safe Fallback / Clarification ---
        return [
            'intent' => self::INTENT_UNKNOWN,
            'layer' => 'layer_3_fallback',
            'order_number' => $orderNumber,
            'confidence' => 0.0,
            'normalized_message' => $normalized,
            'multi_intents' => $multiIntents,
            'context' => $conversationContext,
        ];
    }

    /**
     * Layer 1 Rule-based Scoring Engine.
     */
    public function evaluateLayer1(User $user, string $normalized, ?string $orderNumber, array $context = []): array
    {
        // 0. Deterministic Greetings, Thanks, Goodbye (Zero-token)
        if ($this->isGreeting($normalized)) {
            return [
                'matched' => true,
                'intent' => self::INTENT_GREETING,
                'confidence' => 1.0,
                'order_number' => null,
            ];
        }
        if ($this->isThanks($normalized)) {
            return [
                'matched' => true,
                'intent' => self::INTENT_THANKS,
                'confidence' => 1.0,
                'order_number' => null,
            ];
        }
        if ($this->isGoodbye($normalized)) {
            return [
                'matched' => true,
                'intent' => self::INTENT_GOODBYE,
                'confidence' => 1.0,
                'order_number' => null,
            ];
        }

        // 1. Guardrail checks: Jailbreak or explicit off-topic
        if ($this->isExplicitOutOfScope($normalized)) {
            return [
                'matched' => true,
                'intent' => self::INTENT_OUT_OF_SCOPE,
                'confidence' => 1.0,
                'order_number' => null,
            ];
        }

        // 2. Bot Identity check
        if ($this->isBotIdentity($normalized)) {
            return [
                'matched' => true,
                'intent' => self::INTENT_BOT_IDENTITY,
                'confidence' => 0.95,
                'order_number' => $orderNumber,
            ];
        }

        $primaryRole = $this->resolvePrimaryRole($user);

        // 3. Procedural guide check (per topic or menu)
        if ($orderNumber === null && ($this->isProceduralGuide($normalized) || $this->isGeneralGuideInquiry($normalized))) {
            $guide = $this->knowledgeBase->findGuideByQuery($normalized, $primaryRole);
            if ($guide !== null) {
                return [
                    'matched' => true,
                    'intent' => $guide['intent'],
                    'confidence' => 0.98,
                    'order_number' => null,
                ];
            }
            return [
                'matched' => true,
                'intent' => self::INTENT_GUIDE_TOPICS_MENU,
                'confidence' => 0.95,
                'order_number' => null,
            ];
        }

        // 3b. Strong guide match even without prefix
        if ($orderNumber === null && !str_contains($normalized, 'berapa kali') && !str_contains($normalized, 'pengeluaran') && !str_contains($normalized, 'total belanja')) {
            $guide = $this->knowledgeBase->findGuideByQuery($normalized, $primaryRole);
            if ($guide !== null && $this->isExplicitGuideQuery($normalized)) {
                return [
                    'matched' => true,
                    'intent' => $guide['intent'],
                    'confidence' => 0.95,
                    'order_number' => null,
                ];
            }
        }

        // 4. Conversation context follow-up (e.g. "kalau yang selesai?", "terus bayarnya?", "yang kemarin?")
        $followUp = $this->checkConversationFollowUp($normalized, $context);
        if ($followUp !== null) {
            return [
                'matched' => true,
                'intent' => $followUp['intent'],
                'confidence' => 0.90,
                'order_number' => $followUp['order_number'] ?? $orderNumber,
            ];
        }

        // 5. If specific Order Number is present:
        if ($orderNumber !== null) {
            $intentWithOrder = $this->determineIntentWithOrderNumber($normalized);
            return [
                'matched' => true,
                'intent' => $intentWithOrder,
                'confidence' => 0.95,
                'order_number' => $orderNumber,
            ];
        }

        // 6. Compute scores for each data intent
        $scores = $this->calculateIntentScores($user, $normalized);

        // Sort descending by score
        arsort($scores);
        $topIntent = array_key_first($scores);
        $topScore = $scores[$topIntent] ?? 0;

        // Margin check with second best score
        $values = array_values($scores);
        $secondScore = $values[1] ?? 0;
        $margin = $topScore - $secondScore;

        // Threshold for Layer 1 acceptance:
        // Score >= 45 and (topScore >= 65 or margin >= 8)
        if ($topScore >= 45 && ($topScore >= 65 || $margin >= 8)) {
            return [
                'matched' => true,
                'intent' => $topIntent,
                'confidence' => min(1.0, round($topScore / 100, 2)),
                'order_number' => null,
            ];
        }

        return [
            'matched' => false,
            'intent' => self::INTENT_UNKNOWN,
            'confidence' => round($topScore / 100, 2),
            'order_number' => null,
        ];
    }

    public function resolvePrimaryRole(User $user): string
    {
        $roles = [];
        if (method_exists($user, 'roles')) {
            try {
                $roles = $user->roles()->pluck('roles.name')->all();
            } catch (\Throwable $e) {
            }
        }
        if (!empty($user->role) && !in_array($user->role, $roles, true)) {
            $roles[] = $user->role;
        }

        if (in_array('super_admin', $roles, true) || in_array('platform_owner', $roles, true)) {
            return 'super_admin';
        }
        if (in_array('store_owner', $roles, true) || in_array('store_admin', $roles, true)) {
            return 'store_admin';
        }
        if (in_array('courier', $roles, true)) {
            return 'courier';
        }

        return $user->role ?? 'customer';
    }

    /**
     * Compute rule-based scores (0 - 100) for all domain intents.
     */
    protected function calculateIntentScores(User $user, string $text): array
    {
        $role = $this->resolvePrimaryRole($user);
        $scores = [];

        // --- ORDER_WASHING_STAGE ---
        $washScore = 0;
        if (str_contains($text, 'cuci') && (str_contains($text, 'sudah') || str_contains($text, 'belum') || str_contains($text, 'selesai') || str_contains($text, 'mesin') || str_contains($text, 'lagi') || str_contains($text, 'sedang') || str_contains($text, 'antre') || str_contains($text, 'tahap'))) {
            $washScore += 65;
        }
        if (str_contains($text, 'sudah cuci') || str_contains($text, 'cuci belum') || str_contains($text, 'sudah jadi') || str_contains($text, 'kelar cuci') || str_contains($text, 'lagi cuci') || str_contains($text, 'cuci atau antre')) {
            $washScore += 45;
        }
        if (str_contains($text, 'cuci pakaian') || str_contains($text, 'baju sudah')) {
            $washScore += 20;
        }
        // Penalty if explicitly asking for price or service list or spending
        if (str_contains($text, 'harga') || str_contains($text, 'tarif') || str_contains($text, 'daftar') || str_contains($text, 'berapa') || str_contains($text, 'biaya') || str_contains($text, 'uang') || str_contains($text, 'duit') || str_contains($text, 'pengeluaran') || str_contains($text, 'keluarkan')) {
            $washScore -= 40;
        }
        $scores[self::INTENT_ORDER_WASHING_STAGE] = max(0, $washScore);

        // --- ORDER_WEIGHT ---
        $weightScore = 0;
        if (str_contains($text, 'timbang') || str_contains($text, 'berat') || str_contains($text, 'kg') || str_contains($text, 'kilo')) {
            $weightScore += 65;
            if (str_contains($text, 'sudah') || str_contains($text, 'belum') || str_contains($text, 'berapa') || str_contains($text, 'kiloan') || str_contains($text, 'berat timbangan') || str_contains($text, 'berat cucian')) {
                $weightScore += 40;
            }
        }
        if (str_contains($text, 'berat timbangan') || str_contains($text, 'timbangan cucian') || str_contains($text, 'berat cucian saya')) {
            $weightScore += 50;
        }
        // Penalty if price inquiry
        if (str_contains($text, 'harga') || str_contains($text, 'tarif') || str_contains($text, 'ongkos') || str_contains($text, 'biaya') || str_contains($text, 'ongkir')) {
            $weightScore -= 60;
        }
        $scores[self::INTENT_ORDER_WEIGHT] = max(0, $weightScore);

        // --- ORDER_PAYMENT_STATUS ---
        $payScore = 0;
        if ((str_contains($text, 'bayar') || str_contains($text, 'lunas')) && (str_contains($text, 'sudah') || str_contains($text, 'belum') || str_contains($text, 'status') || str_contains($text, 'tagihan') || str_contains($text, 'bagaimana'))) {
            $payScore += 70;
        }
        if (str_contains($text, 'status bayar') || str_contains($text, 'status pembayaran') || str_contains($text, 'lunas belum') || str_contains($text, 'sudah dibayar')) {
            $payScore += 45;
        }
        if (str_contains($text, 'refund') || str_contains($text, 'pengembalian')) {
            $payScore += 80;
        }
        // Penalty if asking total spending ("total pengeluaran")
        if (str_contains($text, 'total') || str_contains($text, 'pengeluaran') || str_contains($text, 'semua') || str_contains($text, 'berapa kali') || str_contains($text, 'akumulasi')) {
            $payScore -= 40;
        }
        $scores[self::INTENT_ORDER_PAYMENT_STATUS] = max(0, $payScore);

        // --- CUSTOMER_SPENDING ---
        $spendScore = 0;
        if (str_contains($text, 'pengeluaran') || str_contains($text, 'belanja') || str_contains($text, 'keluarkan')) {
            $spendScore += 70;
        }
        if ((str_contains($text, 'total') || str_contains($text, 'habis') || str_contains($text, 'keluar') || str_contains($text, 'rekap') || str_contains($text, 'akumulasi'))
            && (str_contains($text, 'uang') || str_contains($text, 'biaya') || str_contains($text, 'duit') || str_contains($text, 'rupiah') || (str_contains($text, 'harga') && str_contains($text, 'selesai')))) {
            $spendScore += 65;
        }
        if (str_contains($text, 'uang keluar') || str_contains($text, 'duit keluar') || str_contains($text, 'habis berapa') || str_contains($text, 'keluar berapa') || str_contains($text, 'saya bayarkan') || str_contains($text, 'sudah saya bayar') || str_contains($text, 'biaya total')) {
            $spendScore += 75;
        }
        // Penalty if looking for price list ("tarif per kilo", "harga cuci")
        if (str_contains($text, 'per kilo') || str_contains($text, 'per kg') || str_contains($text, 'daftar') || str_contains($text, 'bedcover') || str_contains($text, 'dry clean') || str_contains($text, 'setrika') || str_contains($text, 'paket')) {
            $spendScore -= 50;
        }
        $scores[self::INTENT_CUSTOMER_SPENDING] = max(0, $spendScore);

        // --- CUSTOMER_ORDER_COUNT ---
        $countScore = 0;
        if (str_contains($text, 'berapa kali') || str_contains($text, 'jumlah pesanan') || str_contains($text, 'total pesanan')) {
            $countScore += 65;
        }
        if (str_contains($text, 'berapa') && (str_contains($text, 'pesan') || str_contains($text, 'transaksi') || str_contains($text, 'order') || str_contains($text, 'cuci pakaian') || str_contains($text, 'pencucian'))) {
            $countScore += 55;
        }
        if (str_contains($text, 'riwayat pesanan') || str_contains($text, 'rekap pesanan')) {
            $countScore += 45;
        }
        if (str_contains($text, 'memesan') || str_contains($text, 'saya pesan') || str_contains($text, 'saya order')) {
            $countScore += 30;
        }
        // Penalty if store context, platform context, or weight context
        if (str_contains($text, 'toko') || str_contains($text, 'masuk') || str_contains($text, 'kelola') || str_contains($text, 'platform') || str_contains($text, 'ekosistem') || str_contains($text, 'kg') || str_contains($text, 'kilo') || str_contains($text, 'timbang') || str_contains($text, 'berat') || str_contains($text, 'limit')) {
            $countScore -= 50;
        }
        $scores[self::INTENT_CUSTOMER_ORDER_COUNT] = max(0, $countScore);

        // --- CUSTOMER_LAST_ORDER ---
        $lastScore = 0;
        if (str_contains($text, 'terakhir') || str_contains($text, 'terbaru') || str_contains($text, 'paling akhir') || str_contains($text, 'paling baru')) {
            $lastScore += 75;
        }
        if (str_contains($text, 'kemarin') && (str_contains($text, 'pesanan') || str_contains($text, 'order') || str_contains($text, 'cuci'))) {
            $lastScore += 70;
        }
        $scores[self::INTENT_CUSTOMER_LAST_ORDER] = max(0, $lastScore);

        // --- ORDER_PROGRESS ---
        $progScore = 0;
        if (str_contains($text, 'sampai mana') || str_contains($text, 'nyampe mana') || str_contains($text, 'posisi') || str_contains($text, 'lacak') || str_contains($text, 'tracking') || str_contains($text, 'kabar')) {
            $progScore += 70;
        }
        if (str_contains($text, 'status pesanan') || str_contains($text, 'progres pesanan') || str_contains($text, 'kabar pesanan') || str_contains($text, 'proses pesanan') || str_contains($text, 'status update') || str_contains($text, 'update pesanan') || str_contains($text, 'pesanan aktif') || str_contains($text, 'punya pesanan') || str_contains($text, 'pesanan berjalan')) {
            $progScore += 65;
        }
        if (str_contains($text, 'pesanan saya') && !str_contains($text, 'berapa') && !str_contains($text, 'harga') && !str_contains($text, 'pengeluaran') && !str_contains($text, 'terakhir') && !str_contains($text, 'terbaru')) {
            $progScore += 45;
        }
        if ((str_contains($text, 'pesanan') || str_contains($text, 'pakaian')) && (str_contains($text, 'sampai') || str_contains($text, 'dimana') || str_contains($text, 'gimana') || str_contains($text, 'bagaimana') || str_contains($text, 'dianter'))) {
            $progScore += 55;
        }
        // Penalty if query is specifically asking about payment or complaint
        if (str_contains($text, 'bayar') || str_contains($text, 'lunas') || str_contains($text, 'refund') || str_contains($text, 'komplain') || str_contains($text, 'keluhan') || str_contains($text, 'dispute') || str_contains($text, 'ulasan') || str_contains($text, 'rating')) {
            $progScore = 0;
        }
        $scores[self::INTENT_ORDER_PROGRESS] = max(0, $progScore);

        // --- SERVICES_AND_PRICES ---
        $servScore = 0;
        if (str_contains($text, 'daftar layanan') || str_contains($text, 'layanan apa saja') || str_contains($text, 'menu layanan') || str_contains($text, 'paket cuci')) {
            $servScore += 70;
        }
        if (str_contains($text, 'harga') || str_contains($text, 'tarif') || str_contains($text, 'biaya') || str_contains($text, 'pricelist') || str_contains($text, 'ongkos') || str_contains($text, 'ongkir')) {
            $servScore += 55;
            if (str_contains($text, 'kiloan') || str_contains($text, 'satuan') || str_contains($text, 'dry clean') || str_contains($text, 'setrika') || str_contains($text, 'bedcover') || str_contains($text, 'komplit') || str_contains($text, 'karpet') || str_contains($text, 'sepatu') || str_contains($text, 'tas') || str_contains($text, 'selimut')) {
                $servScore += 30;
            }
        }
        if (str_contains($text, 'layanan') && (str_contains($text, 'ada') || str_contains($text, 'apa') || str_contains($text, 'daftar') || str_contains($text, 'pilihan') || str_contains($text, 'setrika'))) {
            $servScore += 55;
        }
        // Penalty if customer spending context ("total pengeluaran") or store manager own store
        if (str_contains($text, 'total pengeluaran') || str_contains($text, 'saya bayarkan') || str_contains($text, 'sudah saya bayar') || str_contains($text, 'habis') || str_contains($text, 'keluar')) {
            $servScore -= 60;
        }
        if (str_contains($text, 'toko saya')) {
            $servScore -= 40;
        }
        $scores[self::INTENT_SERVICES_AND_PRICES] = max(0, $servScore);

        // --- BUSINESS_RULES ---
        $ruleScore = 0;
        if (str_contains($text, 'cod') && (str_contains($text, 'aturan') || str_contains($text, 'maksimal') || str_contains($text, 'batas') || str_contains($text, 'bisa') || str_contains($text, 'limit') || str_contains($text, 'nominal') || str_contains($text, 'tunai'))) {
            $ruleScore += 90;
        }
        if ((str_contains($text, 'batas') || str_contains($text, 'tenggat') || str_contains($text, 'aturan') || str_contains($text, 'waktu') || str_contains($text, 'maksimal') || str_contains($text, 'garansi') || str_contains($text, 'syarat') || str_contains($text, 'kebijakan') || str_contains($text, 'limit'))
            && (str_contains($text, 'komplain') || str_contains($text, 'dispute') || str_contains($text, 'keluhan') || str_contains($text, 'bayar') || str_contains($text, 'invoice') || str_contains($text, 'refund') || str_contains($text, 'klaim') || str_contains($text, 'konfirmasi') || str_contains($text, 'batal') || str_contains($text, 'delivery'))) {
            $ruleScore += 85;
        }
        if (str_contains($text, 'customer service') || str_contains($text, 'kontak cs') || str_contains($text, 'hubungi cs') || str_contains($text, 'nomor cs') || str_contains($text, 'bantuan cs')) {
            $ruleScore += 90;
        }
        if (str_contains($text, 'kontak') && str_contains($text, 'customer service')) {
            $ruleScore += 90;
        }
        $scores[self::INTENT_BUSINESS_RULES] = max(0, $ruleScore);

        // --- STORE_INFO ---
        $storeScore = 0;
        if (str_contains($text, 'terdekat') || (str_contains($text, 'alamat') && (str_contains($text, 'toko') || str_contains($text, 'laundry') || str_contains($text, 'outlet')))) {
            $storeScore += 75;
        }
        if (str_contains($text, 'jadwal operasional') || str_contains($text, 'jam operasional') || str_contains($text, 'drop off')) {
            $storeScore += 75;
        }
        if (str_contains($text, 'toko') && (str_contains($text, 'jam') || str_contains($text, 'buka') || str_contains($text, 'tutup') || str_contains($text, 'lokasi') || str_contains($text, 'alamat'))) {
            $storeScore += 65;
        }
        if (str_contains($text, 'laundry berkah') || str_contains($text, 'klin laundry') || str_contains($text, 'info toko') || str_contains($text, 'daftar toko')) {
            $storeScore += 60;
        }
        if (str_contains($text, 'pesanan masuk') || str_contains($text, 'orderan toko') || str_contains($text, 'customer service')) {
            $storeScore -= 50;
        }
        $scores[self::INTENT_STORE_INFO] = max(0, $storeScore);

        // --- COMPLAINT_OR_REVIEW ---
        $compScore = 0;
        if (str_contains($text, 'komplain') || str_contains($text, 'keluhan') || str_contains($text, 'dispute')) {
            $compScore += 80;
            // Penalty if asking for business rule/policy
            if (str_contains($text, 'batas') || str_contains($text, 'waktu') || str_contains($text, 'aturan') || str_contains($text, 'tenggat') || str_contains($text, 'jam')) {
                $compScore -= 50;
            }
        }
        if (str_contains($text, 'ulasan') || str_contains($text, 'review') || str_contains($text, 'rating')) {
            $compScore += 75;
        }
        $scores[self::INTENT_COMPLAINT_OR_REVIEW] = max(0, $compScore);

        // --- ACCOUNT_INFO ---
        $accScore = 0;
        if (str_contains($text, 'akun') && (str_contains($text, 'nama') || str_contains($text, 'saya') || str_contains($text, 'profil') || str_contains($text, 'data') || str_contains($text, 'info') || str_contains($text, 'email') || str_contains($text, 'telepon') || str_contains($text, 'terdaftar'))) {
            $accScore += 75;
        }
        if (str_contains($text, 'siapa saya') || str_contains($text, 'siapa nama saya') || str_contains($text, 'peran saya') || str_contains($text, 'role saya') || str_contains($text, 'data diri') || str_contains($text, 'nama saya di akun') || str_contains($text, 'data saya') || str_contains($text, 'tersimpan')) {
            $accScore += 80;
        }
        if (str_contains($text, 'nama') && (str_contains($text, 'nomor telepon') || str_contains($text, 'telepon') || str_contains($text, 'terdaftar') || str_contains($text, 'identitas'))) {
            $accScore += 85;
        }
        if (str_contains($text, 'alamat') && (str_contains($text, 'saya') || str_contains($text, 'tersimpan') || str_contains($text, 'terdaftar') || str_contains($text, 'pengiriman'))) {
            $accScore += 75;
        }
        $scores[self::INTENT_ACCOUNT_INFO] = max(0, $accScore);

        // --- COURIER_TASKS (Only relevant for courier / users asking tasks) ---
        $courierScore = 0;
        if (str_contains($text, 'tugas aktif') || str_contains($text, 'tugas kurir') || str_contains($text, 'tugas saya') || str_contains($text, 'jemputan saya')) {
            $courierScore += 75;
        }
        if (str_contains($text, 'tugas') && (str_contains($text, 'kurir') || str_contains($text, 'antar') || str_contains($text, 'jemput') || str_contains($text, 'aktif'))) {
            $courierScore += 65;
        }
        if (str_contains($text, 'jemput') && (str_contains($text, 'hari ini') || str_contains($text, 'saya') || str_contains($text, 'berapa') || str_contains($text, 'dimana'))) {
            $courierScore += 70;
        }
        if (str_contains($text, 'pengantaran') && (str_contains($text, 'total') || str_contains($text, 'saya') || str_contains($text, 'tugas') || str_contains($text, 'selesaikan') || str_contains($text, 'daftar'))) {
            $courierScore += 70;
        }
        if ($role === 'courier') {
            $courierScore += 35;
            if (str_contains($text, 'tugas')) {
                $courierScore += 40;
            }
        }
        // Penalty if COD rule question
        if (str_contains($text, 'cod') && (str_contains($text, 'aturan') || str_contains($text, 'maksimal') || str_contains($text, 'batas') || str_contains($text, 'limit'))) {
            $courierScore -= 60;
        }
        $scores[self::INTENT_COURIER_TASKS] = max(0, $courierScore);

        // --- STORE_SERVICES_DATA (Mitra asking own store prices/services) ---
        $stSvcScore = 0;
        if (in_array($role, ['store_admin', 'store_owner'], true) || str_contains($text, 'toko saya')) {
            if ((str_contains($text, 'harga') || str_contains($text, 'tarif') || str_contains($text, 'layanan')) && (str_contains($text, 'toko') || str_contains($text, 'toko saya'))) {
                $stSvcScore += 90;
            }
        }
        $scores[self::INTENT_STORE_SERVICES_DATA] = max(0, $stSvcScore);

        // --- STORE_COMPLAINTS_DATA (Mitra asking complaints in own store) ---
        $stCompScore = 0;
        if (in_array($role, ['store_admin', 'store_owner'], true) || str_contains($text, 'toko saya')) {
            if ((str_contains($text, 'komplain') || str_contains($text, 'keluhan')) && (str_contains($text, 'toko') || str_contains($text, 'pelanggan') || str_contains($text, 'toko saya'))) {
                $stCompScore += 90;
            }
        }
        $scores[self::INTENT_STORE_COMPLAINTS_DATA] = max(0, $stCompScore);

        // --- STORE_HOURS_DATA (Mitra asking own store hours) ---
        $stHoursScore = 0;
        if (in_array($role, ['store_admin', 'store_owner'], true) || str_contains($text, 'toko saya')) {
            if ((str_contains($text, 'jam buka') || str_contains($text, 'jam operasional') || str_contains($text, 'jadwal buka') || (str_contains($text, 'jam') && str_contains($text, 'buka'))) && (str_contains($text, 'toko') || str_contains($text, 'toko saya'))) {
                $stHoursScore += 90;
            }
        }
        $scores[self::INTENT_STORE_HOURS_DATA] = max(0, $stHoursScore);

        // --- STORE_ORDERS (Store managers) ---
        $stOrderScore = 0;
        if (str_contains($text, 'pesanan masuk') || str_contains($text, 'orderan toko') || str_contains($text, 'pesanan toko') || str_contains($text, 'toko hari ini') || (str_contains($text, 'toko') && str_contains($text, 'pesanan'))) {
            $stOrderScore += 80;
        }
        if (str_contains($text, 'toko saya') && (str_contains($text, 'pesanan') || str_contains($text, 'order') || str_contains($text, 'berapa'))) {
            $stOrderScore += 65;
        }
        if (in_array($role, ['store_admin', 'store_owner'], true) && (str_contains($text, 'pesanan masuk') || (str_contains($text, 'toko') && str_contains($text, 'pesan')))) {
            $stOrderScore += 30;
        }
        // Penalty if personal customer context, platform context, or performa/omzet
        if (str_contains($text, 'saya memesan') || str_contains($text, 'saya pesan') || str_contains($text, 'cucian saya') || str_contains($text, 'berapa kali') || str_contains($text, 'platform') || str_contains($text, 'performa') || str_contains($text, 'omzet') || str_contains($text, 'statistik') || str_contains($text, 'paling baru saya') || str_contains($text, 'terbaru saya')) {
            $stOrderScore -= 80;
        }
        $scores[self::INTENT_STORE_ORDERS] = max(0, $stOrderScore);

        // --- STORE_STATS (Store managers) ---
        $stStatsScore = 0;
        if (str_contains($text, 'statistik toko') || str_contains($text, 'performa toko') || str_contains($text, 'omzet toko') || str_contains($text, 'rekap toko') || str_contains($text, 'ringkasan toko') || (str_contains($text, 'toko') && (str_contains($text, 'statistik') || str_contains($text, 'performa') || str_contains($text, 'omzet') || str_contains($text, 'rekap')))) {
            $stStatsScore += 80;
        }
        if (in_array($role, ['store_admin', 'store_owner'], true) && str_contains($text, 'toko')) {
            $stStatsScore += 40;
        }
        if (str_contains($text, 'platform') || str_contains($text, 'pesanan masuk')) {
            $stStatsScore -= 40;
        }
        $scores[self::INTENT_STORE_STATS] = max(0, $stStatsScore);

        // --- SUPER_ADMIN_PLATFORM ---
        $adminScore = 0;
        if (str_contains($text, 'ringkasan platform') || str_contains($text, 'overview sistem') || str_contains($text, 'statistik platform') || str_contains($text, 'total platform') || str_contains($text, 'ringkasan pesanan platform') || str_contains($text, 'statistik keseluruhan') || str_contains($text, 'se platform') || str_contains($text, 'ekosistem')) {
            $adminScore += 85;
        }
        if (str_contains($text, 'semua pesanan') || str_contains($text, 'seluruh pesanan') || (str_contains($text, 'platform') && (str_contains($text, 'total') || str_contains($text, 'pesanan') || str_contains($text, 'transaksi') || str_contains($text, 'ringkasan') || str_contains($text, 'sistem') || str_contains($text, 'ekosistem')))) {
            $adminScore += 80;
        }
        if (str_contains($text, 'toko mitra yang aktif') || str_contains($text, 'jumlah toko mitra') || str_contains($text, 'toko aktif di sistem') || str_contains($text, 'mitra yang aktif')) {
            $adminScore += 90;
        }
        if (in_array($role, ['super_admin', 'platform_owner', 'platform_admin'], true)) {
            if (str_contains($text, 'platform') || str_contains($text, 'sistem') || str_contains($text, 'keseluruhan') || str_contains($text, 'ekosistem') || str_contains($text, 'toko mitra')) {
                $adminScore += 50;
            }
        }
        $scores[self::INTENT_SUPER_ADMIN_PLATFORM] = max(0, $adminScore);

        // --- STORE_COURIERS_DATA ---
        $stCourierScore = 0;
        if (str_contains($text, 'kurir') && !str_contains($text, 'pelamar') && !str_contains($text, 'kandidat')) {
            if (str_contains($text, 'toko') || in_array($role, ['store_admin', 'store_owner'], true)) {
                $stCourierScore += 85;
            }
        }
        $scores[self::INTENT_STORE_COURIERS_DATA] = max(0, $stCourierScore);

        // --- STORE_APPLICANTS_DATA ---
        $stAppScore = 0;
        if (str_contains($text, 'pelamar') || str_contains($text, 'kandidat') || str_contains($text, 'rekrut')) {
            $stAppScore += 90;
        }
        $scores[self::INTENT_STORE_APPLICANTS_DATA] = max(0, $stAppScore);

        // --- COURIER_STORE_INFO ---
        $crStoreScore = 0;
        if ($role === 'courier' && (str_contains($text, 'toko') || str_contains($text, 'mitra') || str_contains($text, 'terikat') || str_contains($text, 'bertugas'))) {
            $crStoreScore += 85;
        }
        $scores[self::INTENT_COURIER_STORE_INFO] = max(0, $crStoreScore);

        // --- SUPER_ADMIN_SEARCH ---
        $adminSearchScore = 0;
        if (str_contains($text, 'cari user') || str_contains($text, 'cari pengguna') || str_contains($text, 'cari akun') || str_contains($text, 'cari pelanggan') || str_contains($text, 'info user') || str_contains($text, 'info pengguna')) {
            $adminSearchScore += 95;
        } elseif (in_array($role, ['super_admin', 'platform_owner', 'platform_admin'], true) && (str_contains($text, 'cari toko') || str_contains($text, 'cari outlet') || str_contains($text, 'info toko mitra'))) {
            $adminSearchScore += 95;
        }
        $scores[self::INTENT_SUPER_ADMIN_SEARCH] = max(0, $adminSearchScore);

        return $scores;
    }

    /**
     * Determine intent when an order number is explicitly stated in query.
     */
    protected function determineIntentWithOrderNumber(string $normalized): string
    {
        if (str_contains($normalized, 'kurir') || str_contains($normalized, 'tugas')) {
            return self::INTENT_COURIER_TASKS;
        }
        if (str_contains($normalized, 'bayar') || str_contains($normalized, 'lunas') || str_contains($normalized, 'tagihan') || str_contains($normalized, 'refund') || str_contains($normalized, 'pengembalian')) {
            return self::INTENT_ORDER_PAYMENT_STATUS;
        }
        if (str_contains($normalized, 'komplain') || str_contains($normalized, 'keluhan') || str_contains($normalized, 'dispute') || str_contains($normalized, 'ulasan') || str_contains($normalized, 'rating') || str_contains($normalized, 'review')) {
            return self::INTENT_COMPLAINT_OR_REVIEW;
        }
        if (str_contains($normalized, 'timbang') || str_contains($normalized, 'berat')) {
            return self::INTENT_ORDER_WEIGHT;
        }
        if (str_contains($normalized, 'cuci') || str_contains($normalized, 'selesai') || str_contains($normalized, 'mesin')) {
            return self::INTENT_ORDER_WASHING_STAGE;
        }

        return self::INTENT_ORDER_PROGRESS;
    }

    /**
     * Check if query is a short follow-up in the same conversation session.
     */
    protected function checkConversationFollowUp(string $normalized, array $context): ?array
    {
        $lastIntent = $context['last_intent'] ?? null;
        $lastOrderNumber = $context['last_order_number'] ?? null;

        if (!$lastIntent) {
            return null;
        }

        $wordCount = str_word_count($normalized);

        // Follow-up 1: "kalau yang selesai?", "yang beres?", "yang kelar?"
        if ($wordCount <= 4 && (str_contains($normalized, 'selesai') || str_contains($normalized, 'kelar') || str_contains($normalized, 'beres'))) {
            return [
                'intent' => self::INTENT_CUSTOMER_ORDER_COUNT,
                'order_number' => null,
            ];
        }

        // Follow-up 2: "yang kemarin?", "kalau yang kemarin?"
        if ($wordCount <= 4 && str_contains($normalized, 'kemarin')) {
            return [
                'intent' => self::INTENT_CUSTOMER_LAST_ORDER,
                'order_number' => null,
            ];
        }

        // Follow-up 3: "terus bayarnya?", "gimana bayarnya?", "bayar nya?"
        if ($wordCount <= 4 && (str_contains($normalized, 'bayar') || str_contains($normalized, 'lunas'))) {
            return [
                'intent' => self::INTENT_ORDER_PAYMENT_STATUS,
                'order_number' => $lastOrderNumber,
            ];
        }

        return null;
    }

    /**
     * Detect compound multi-intents in a single query.
     */
    public function detectMultiIntents(string $normalized): array
    {
        $multi = [];

        // Sub-intent 1: Bot Identity
        if ($this->isBotIdentity($normalized)) {
            $multi[] = self::INTENT_BOT_IDENTITY;
        }

        // Sub-intent 2: Greeting
        if (str_starts_with($normalized, 'halo') || str_contains($normalized, 'halo landy') || str_starts_with($normalized, 'hai')) {
            $multi[] = self::INTENT_GREETING;
        }

        // Sub-intent 3: Thanks
        if (str_contains($normalized, 'terima kasih') || str_contains($normalized, 'makasih')) {
            $multi[] = self::INTENT_THANKS;
        }

        // Sub-intent 4: Order Progress
        if (str_contains($normalized, 'sampai mana') || str_contains($normalized, 'posisi') || str_contains($normalized, 'kabar pesanan') || str_contains($normalized, 'estimasi selesainya') || str_contains($normalized, 'pesanan saya sampai')) {
            $multi[] = self::INTENT_ORDER_PROGRESS;
        }

        // Sub-intent 5: Washing Stage
        if ((str_contains($normalized, 'cuci') || str_contains($normalized, 'dicuci')) && (str_contains($normalized, 'sudah') || str_contains($normalized, 'sedang') || str_contains($normalized, 'belum') || str_contains($normalized, 'apakah'))) {
            $multi[] = self::INTENT_ORDER_WASHING_STAGE;
        }

        // Sub-intent 6: Order Weight
        if (str_contains($normalized, 'berat') || str_contains($normalized, 'timbang') || str_contains($normalized, 'timbangan') || str_contains($normalized, 'ditimbang')) {
            $multi[] = self::INTENT_ORDER_WEIGHT;
        }

        // Sub-intent 7: Payment status
        if (str_contains($normalized, 'sudah dibayar') || str_contains($normalized, 'lunas') || str_contains($normalized, 'status bayar')) {
            $multi[] = self::INTENT_ORDER_PAYMENT_STATUS;
        }

        // Sub-intent 8: Customer Order Count
        if (str_contains($normalized, 'berapa kali') || str_contains($normalized, 'jumlah pesanan saya')) {
            $multi[] = self::INTENT_CUSTOMER_ORDER_COUNT;
        }

        // Sub-intent 9: Customer Spending
        if (str_contains($normalized, 'pengeluaran') || str_contains($normalized, 'total belanja')) {
            $multi[] = self::INTENT_CUSTOMER_SPENDING;
        }

        // Sub-intent 10: Customer Last Order
        if (str_contains($normalized, 'pesanan terakhir') || str_contains($normalized, 'order terakhir') || str_contains($normalized, 'nomor pesanan terakhir')) {
            $multi[] = self::INTENT_CUSTOMER_LAST_ORDER;
        }

        // Sub-intent 11: Business Rules (COD or Dispute limits)
        if ((str_contains($normalized, 'cod') && (str_contains($normalized, 'aturan') || str_contains($normalized, 'maksimal') || str_contains($normalized, 'batas') || str_contains($normalized, 'bayar') || str_contains($normalized, 'duit') || str_contains($normalized, 'limit')))
            || str_contains($normalized, 'batas waktu komplain') || str_contains($normalized, 'customer service')) {
            $multi[] = self::INTENT_BUSINESS_RULES;
        }

        // Sub-intent 12: General Services and Prices
        if ((str_contains($normalized, 'daftar layanan') || str_contains($normalized, 'daftar harga layanan') || str_contains($normalized, 'paket cuci')) && !str_contains($normalized, 'toko saya')) {
            $multi[] = self::INTENT_SERVICES_AND_PRICES;
        }

        // Sub-intent 13: Courier tasks
        if (str_contains($normalized, 'tugas aktif') || str_contains($normalized, 'tugas kurir') || str_contains($normalized, 'tugas saya')) {
            $multi[] = self::INTENT_COURIER_TASKS;
        }

        // Sub-intent 14: Store orders & stats
        if (str_contains($normalized, 'pesanan masuk toko')) {
            $multi[] = self::INTENT_STORE_ORDERS;
        }
        if (str_contains($normalized, 'omzet') || str_contains($normalized, 'performa toko') || str_contains($normalized, 'statistik toko')) {
            $multi[] = self::INTENT_STORE_STATS;
        }

        // Sub-intent 15: Store specific prices & hours & complaints & rating
        if (str_contains($normalized, 'daftar harga di toko saya') || str_contains($normalized, 'harga di toko saya')) {
            $multi[] = self::INTENT_STORE_SERVICES_DATA;
        }
        if (str_contains($normalized, 'jam buka toko') || str_contains($normalized, 'jam buka di toko')) {
            $multi[] = self::INTENT_STORE_HOURS_DATA;
        }
        if (str_contains($normalized, 'komplain di toko') || str_contains($normalized, 'komplain toko')) {
            $multi[] = self::INTENT_STORE_COMPLAINTS_DATA;
        }
        if (str_contains($normalized, 'rating toko')) {
            $multi[] = self::INTENT_COMPLAINT_OR_REVIEW;
        }

        // Sub-intent 16: Admin Platform
        if (str_contains($normalized, 'ringkasan platform') || str_contains($normalized, 'total transaksi sistem')) {
            $multi[] = self::INTENT_SUPER_ADMIN_PLATFORM;
        }

        // Sub-intent 17: Procedural guides in compound queries
        if (str_contains($normalized, 'cara pesan') || str_contains($normalized, 'bagaimana cara pesan') || str_contains($normalized, 'cara memesan') || str_contains($normalized, 'cara order')) {
            $multi[] = self::INTENT_GUIDE_ORDER;
        }
        if (str_contains($normalized, 'cara bayar') || str_contains($normalized, 'cara membayarnya') || str_contains($normalized, 'metode bayar') || str_contains($normalized, 'metode pembayaran')) {
            $multi[] = self::INTENT_GUIDE_PAYMENT;
        }
        if (str_contains($normalized, 'cara batalkan') || str_contains($normalized, 'cara cancel')) {
            $multi[] = self::INTENT_GUIDE_CANCEL;
        }
        if (str_contains($normalized, 'cara ajukan komplain') || str_contains($normalized, 'langkah mengajukannya') || (str_contains($normalized, 'cara') && str_contains($normalized, 'komplain'))) {
            $multi[] = self::INTENT_GUIDE_COMPLAINT;
        }
        if (str_contains($normalized, 'cara memberi rating') || str_contains($normalized, 'cara ulasan') || str_contains($normalized, 'memberi rating') || (str_contains($normalized, 'cara') && str_contains($normalized, 'rating'))) {
            $multi[] = self::INTENT_GUIDE_REVIEW;
        }
        if (str_contains($normalized, 'cara tambah alamat') || str_contains($normalized, 'cara ubah alamat')) {
            $multi[] = self::INTENT_GUIDE_ADDRESS;
        }
        if (str_contains($normalized, 'cara gabung mitra') || str_contains($normalized, 'cara daftar mitra') || str_contains($normalized, 'gabung mitra') || str_contains($normalized, 'daftar mitra') || str_contains($normalized, 'persyaratannya')) {
            $multi[] = self::INTENT_GUIDE_UPGRADE;
        }
        if (str_contains($normalized, 'cara terima tugas') || str_contains($normalized, 'cara ambil tugas')) {
            $multi[] = self::INTENT_GUIDE_COURIER_TASKS;
        }
        if (str_contains($normalized, 'cara setor cod') || str_contains($normalized, 'cara tagih cod')) {
            $multi[] = self::INTENT_GUIDE_COURIER_COD;
        }
        if (str_contains($normalized, 'cara tambah layanan')) {
            $multi[] = self::INTENT_GUIDE_STORE_SERVICES;
        }
        if (str_contains($normalized, 'cara timbang cucian') || str_contains($normalized, 'cara timbang')) {
            $multi[] = self::INTENT_GUIDE_STORE_WEIGHING;
        }

        $unique = array_values(array_unique($multi));
        return count($unique) >= 2 ? $unique : [];
    }

    /**
     * Layer 2 Model Router Fallback.
     * Invokes router model with strict temperature 0 and no user data.
     */
    public function evaluateLayer2(User $user, string $normalized, ?string $orderNumber, ?string $modelOverride = null): array
    {
        $routerModel = $modelOverride ?? config('ai.router_model', config('ai.model', 'liquid/lfm-2.5-2.6b:free'));
        $apiKey = config('ai.api_key', config('ai.openai_api_key'));

        if (empty($apiKey) || !$this->client || $this->client instanceof \Tests\Feature\FakeAiClient || $this->client instanceof \Tests\Feature\FakeTrackingAiClient) {
            return ['intent' => self::INTENT_UNKNOWN, 'order_number' => $orderNumber, 'confidence' => 0.0, 'router_model' => $routerModel];
        }

        $allowedList = implode(', ', self::ALL_INTENTS);
        $role = $user->role ?? 'customer';

        $systemPrompt = "You are a strict intent classification router for LaundryKu marketplace.
Your task is to classify the user query into exactly ONE intent label from this closed list:
[{$allowedList}]

Rules:
1. Do not hallucinate or guess data.
2. If the user asks how to do something (procedural instructions), return 'procedural_guide'.
3. If the query is off-topic (cooking, politics, coding, homework), return 'out_of_scope'.
4. If ambiguous or unclear, return 'unknown'.
5. Return strictly a JSON object: {\"intent\": \"<LABEL>\", \"order_number\": \"<ORD-...>\" or null, \"confidence\": 0.0-1.0}";

        $userPrompt = "Role: {$role}\nQuery: {$normalized}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $startTime = microtime(true);
            // Save and temporarily override model for the router call
            $origModel = config('ai.model');
            config(['ai.model' => $routerModel]);

            $response = $this->client->chat($messages, []);

            config(['ai.model' => $origModel]);
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            $rawContent = trim((string) ($response['content'] ?? ''));

            // Extract JSON from response
            if (preg_match('/\{.*\}/s', $rawContent, $m)) {
                $parsed = json_decode($m[0], true);
                if (is_array($parsed) && isset($parsed['intent'])) {
                    $intent = strtolower(trim($parsed['intent']));
                    if (in_array($intent, self::ALL_INTENTS, true)) {
                        $parsedOrderNum = !empty($parsed['order_number']) ? strtoupper(trim($parsed['order_number'])) : null;
                        // Parameter validation: order number must match ORD-... pattern
                        if ($parsedOrderNum !== null && !preg_match('/^ORD-[A-Za-z0-9\-]+$/i', $parsedOrderNum)) {
                            return [
                                'intent' => self::INTENT_UNKNOWN,
                                'order_number' => null,
                                'confidence' => 0.0,
                                'latency_ms' => $latencyMs,
                                'router_model' => $routerModel,
                            ];
                        }

                        return [
                            'intent' => $intent,
                            'order_number' => $parsedOrderNum ?? $orderNumber,
                            'confidence' => (float) ($parsed['confidence'] ?? 0.85),
                            'latency_ms' => $latencyMs,
                            'router_model' => $routerModel,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AI Router Model] Call failed: ' . $e->getMessage());
        }

        return [
            'intent' => self::INTENT_UNKNOWN,
            'order_number' => $orderNumber,
            'confidence' => 0.0,
            'router_model' => $routerModel,
        ];
    }

    /**
     * Layer 3 Safe Clarification response.
     * Tailored by user role without ever asking for order ID for general queries.
     */
    public function getClarificationResponse(User $user): string
    {
        $role = $user->role ?? 'customer';

        if ($role === 'courier') {
            return "Maaf, saya belum memahami maksud pertanyaan Anda. Sebagai kurir di LaundryKu, Anda dapat menanyakan:\n"
                . "1. Tugas aktif saat ini (contoh: \"tugas kurir saya\")\n"
                . "2. Riwayat tugas pengantaran/penjemputan (contoh: \"berapa tugas kurir saya\")\n"
                . "3. Status pesanan terkait (contoh: \"status pesanan ORD-...\")\n"
                . "Silakan tanyakan salah satu hal di atas!";
        }

        if (in_array($role, ['store_admin', 'store_owner'], true)) {
            return "Maaf, saya belum memahami maksud pertanyaan Anda. Sebagai pengelola toko mitra di LaundryKu, Anda dapat menanyakan:\n"
                . "1. Pesanan masuk toko (contoh: \"pesanan masuk toko saya\")\n"
                . "2. Statistik dan ringkasan toko (contoh: \"statistik toko saya\")\n"
                . "3. Rincian status pesanan spesifik (contoh: \"status pesanan ORD-...\")\n"
                . "Silakan tanyakan salah satu hal di atas!";
        }

        if (in_array($role, ['super_admin', 'platform_owner', 'platform_admin'], true)) {
            return "Maaf, saya belum memahami maksud pertanyaan Anda. Sebagai admin platform LaundryKu, Anda dapat menanyakan:\n"
                . "1. Ringkasan performa platform (contoh: \"ringkasan platform\")\n"
                . "2. Pengecekan pesanan di seluruh sistem (contoh: \"status pesanan ORD-...\")\n"
                . "3. Daftar layanan dan aturan sistem (contoh: \"daftar layanan\" atau \"aturan cod\")\n"
                . "Silakan tanyakan salah satu hal di atas!";
        }

        // Customer default
        return "Maaf, saya belum memahami maksud pertanyaan Anda seputar layanan LaundryKu. Anda dapat menanyakan hal-hal berikut:\n"
            . "1. Status cucian Anda (contoh: \"pesanan saya sampai mana\" atau \"apakah sudah dicuci\")\n"
            . "2. Riwayat dan pengeluaran (contoh: \"berapa kali saya memesan\" atau \"total pengeluaran saya\")\n"
            . "3. Layanan & harga laundry (contoh: \"daftar layanan\" atau \"harga cuci komplit\")\n"
            . "4. Komplain & ulasan (contoh: \"komplain saya\" atau \"ulasan saya\")\n"
            . "Silakan ajukan salah satu pertanyaan di atas!";
    }

    /**
     * Check if query is simple greeting.
     */
    public function isGreeting(string $normalized): bool
    {
        $normalized = trim($normalized);
        if (str_contains($normalized, 'cara') || str_contains($normalized, 'pesan') || str_contains($normalized, 'tugas') || str_contains($normalized, 'status') || str_contains($normalized, 'harga') || str_contains($normalized, 'toko')) {
            return false;
        }

        $greetings = ['halo', 'hai', 'hi', 'assalamualaikum', 'assalamu\'alaikum', 'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam', 'halo landy', 'hai landy'];
        foreach ($greetings as $g) {
            if ($normalized === $g || str_starts_with($normalized, $g . ' ') || str_ends_with($normalized, ' ' . $g)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if query is thank-you.
     */
    public function isThanks(string $normalized): bool
    {
        $normalized = trim($normalized);
        if (str_contains($normalized, 'cara') || str_contains($normalized, 'rating') || str_contains($normalized, 'ulasan') || str_contains($normalized, 'pesan')) {
            return false;
        }

        $thanks = ['terima kasih', 'makasih', 'tengkyu', 'thanks', 'terimakasih', 'thx', 'ok terima kasih', 'oke terima kasih', 'terima kasih banyak', 'makasih ya', 'makasih min'];
        foreach ($thanks as $t) {
            if ($normalized === $t || str_starts_with($normalized, $t . ' ') || str_ends_with($normalized, ' ' . $t) || str_contains($normalized, $t)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if query is goodbye / parting.
     */
    public function isGoodbye(string $normalized): bool
    {
        $normalized = trim($normalized);
        $goodbyes = ['sampai jumpa', 'selamat tinggal', 'dadah', 'bye', 'byebye', 'pamit', 'dadah min', 'bye min'];
        foreach ($goodbyes as $gb) {
            if ($normalized === $gb || str_starts_with($normalized, $gb . ' ') || str_ends_with($normalized, ' ' . $gb)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if query is explicitly asking for guide menu / topics.
     */
    public function isGeneralGuideInquiry(string $normalized): bool
    {
        $patterns = [
            'panduan cara pakai', 'panduan aplikasi', 'cara pakai aplikasi', 'panduan bantuan',
            'petunjuk penggunaan', 'menu panduan', 'bantuan aplikasi', 'bantuan kurir',
            'panduan mitra', 'bantuan mitra', 'panduan untuk mitra', 'panduan bantuan untuk mitra',
        ];
        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if query contains strong procedural words.
     */
    public function isExplicitGuideQuery(string $normalized): bool
    {
        $patterns = [
            'tutorial', 'langkah', 'tahapan', 'prosedur', 'cara', 'petunjuk',
            'bisa pesan', 'bisa jemput', 'bisa order', 'gimana langkah',
            'cra psn', 'cra byr', 'cra order', 'batalin order', 'batalkan pesanan',
            'tambah alamat', 'ubah alamat', 'tmbah almt', 'atur jam operasional', 'atur jam buka',
        ];
        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if query is explicitly procedural (how-to).
     */
    protected function isProceduralGuide(string $normalized): bool
    {
        $proceduralPrefixes = [
            'bagaimana cara', 'gimana cara', 'panduan ', 'tutorial ',
            'langkah ', 'tahapan ', 'gimana langkah', 'prosedur ',
            'bisa pesan', 'bisa jemput', 'bisa order', 'apakah bisa jemput', 'apakah bisa pesan',
            'cara memesan', 'cara melacak', 'cara bayar', 'cara komplain', 'cara daftar',
            'tutorial order', 'cra daftar', 'cra komplain', 'petunjuk penggunaan',
            'cra psn', 'cra byr', 'cra order', 'batalin order', 'tambah alamat', 'atur jam operasional', 'atur jam buka',
        ];

        if (str_starts_with($normalized, 'cara ') || str_contains($normalized, ' cara ')) {
            return true;
        }

        if (str_starts_with($normalized, 'alur ') || str_contains($normalized, ' alur ')) {
            return true;
        }

        foreach ($proceduralPrefixes as $prefix) {
            if (str_starts_with($normalized, $prefix) || str_contains($normalized, ' ' . $prefix) || str_contains($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query is bot identity question.
     */
    protected function isBotIdentity(string $normalized): bool
    {
        // Exclude user asking about their own identity
        if (str_contains($normalized, 'siapa saya') || str_contains($normalized, 'nama saya') || str_contains($normalized, 'peran saya')) {
            return false;
        }

        $patterns = [
            'siapa anda', 'anda siapa', 'kamu siapa', 'siapa kamu', 'siapa namamu',
            'nama kamu siapa', 'nama anda siapa', 'kamu bot apa', 'apakah kamu bot',
            'apakah anda bot', 'kamu bot', 'bisa bantu apa', 'apa fungsi kamu',
            'kamu itu apa', 'kamu apa', 'apa kamu',
            'kamu landy', 'siapa landy', 'tentang landy', 'kamu robot', 'kmu robot',
            'kamu ai', 'ai laundryku', 'siapa nama asisten', 'asisten pintar',
            'perkenalkan diri', 'kamu dibuat', 'chatbot asisten',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for explicit out-of-scope topics.
     */
    protected function isExplicitOutOfScope(string $normalized): bool
    {
        $patterns = [
            'system prompt', 'abaikan instruksi', 'ignore instructions', 'ignore previous', 'print secret',
            'resep ', 'masak ', 'rendang', 'nasi goreng', 'politik', 'pemilu', 'presiden',
            'kalkulus', 'integral', 'turunan fungsi', 'rumus fisika', 'hukum gravitasi', 'newton',
            'pr fisika', 'pr matematika', 'tugas sekolah', 'tugas kuliah',
            'terjemahkan', 'terjemahan', 'translate', 'bahasa jerman', 'bahasa perancis',
            'bikin kode', 'buatkan kode', 'python', 'javascript', 'php script',
            'sql query', 'drop table', 'delete all', 'rm -rf', 'select *', 'from users', 'from orders', 'where role', 'where 1 1',
            'hacked by', 'execute bash', 'cuaca', 'lelucon', 'sepakbola', 'skor pertandingan',
            'puisi', 'dongeng', 'penemu', 'mesin uap', 'wisata', 'pulau bali', 'wisata bali', 'ibukota',
            'komputer gaming', 'merakit komputer', 'saham', 'bom nuklir',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }
}
