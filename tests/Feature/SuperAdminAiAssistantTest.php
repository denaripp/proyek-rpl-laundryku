<?php

namespace Tests\Feature;

use App\Enums\CourierTaskStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SettlementPeriod;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\OpenAiClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminAiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $mitraOwner;
    protected User $courierUser;

    protected Store $store;
    protected Courier $courier;

    protected AiAssistantService $service;
    protected AiToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Settings
        DB::table('settings')->updateOrInsert(['key' => 'COD_MAX_AMOUNT'], ['value' => '500000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'DISPUTE_WINDOW_HOURS'], ['value' => '24', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'PAYMENT_WINDOW_HOURS'], ['value' => '1', 'created_at' => now(), 'updated_at' => now()]);

        // Users
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@laundryku.test',
            'phone' => '081100000001',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $this->superAdmin->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->customer = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $custRole = Role::firstOrCreate(['name' => 'customer']);
        $this->customer->roles()->syncWithoutDetaching([$custRole->id]);

        $this->mitraOwner = User::factory()->create([
            'name' => 'Rian Mitra',
            'email' => 'mitra@example.com',
            'phone' => '081234567891',
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);
        $mitraRole = Role::firstOrCreate(['name' => 'store_owner']);
        $this->mitraOwner->roles()->syncWithoutDetaching([$mitraRole->id]);

        $this->courierUser = User::factory()->create([
            'name' => 'Ahmad Kurir',
            'email' => 'kurir@example.com',
            'phone' => '081234567892',
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);
        $courierRole = Role::firstOrCreate(['name' => 'courier']);
        $this->courierUser->roles()->syncWithoutDetaching([$courierRole->id]);

        // Store
        $this->store = Store::factory()->create([
            'name' => 'Berkah Laundry',
            'slug' => 'berkah-laundry',
            'owner_user_id' => $this->mitraOwner->id,
            'address' => 'Jl. Mawar No. 10',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Courier profile
        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B 1234 CD',
            'is_verified' => true,
            'is_available' => true,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8456,
        ]);

        config(['ai.mock_in_local' => true, 'ai.api_key' => '']);
        $this->registry = app(AiToolRegistry::class);
        $this->service = app(AiAssistantService::class);
    }

    public function test_super_admin_can_query_user_stats_and_search_users(): void
    {
        // 1. User stats per role
        $statsRes = $this->service->chat($this->superAdmin, null, 'Berapa customer, admin toko, dan kurir yang terdaftar?');
        $this->assertFalse($statsRes['fallback']);
        $this->assertStringContainsString('Pelanggan', $statsRes['reply']);
        $this->assertStringContainsString('Kurir', $statsRes['reply']);
        $this->assertStringContainsString('Pengelola Toko', $statsRes['reply']);

        // 2. Search user by name
        $searchRes = $this->service->chat($this->superAdmin, null, 'Cari pengguna bernama Budi');
        $this->assertFalse($searchRes['fallback']);
        $this->assertStringContainsString('Budi Santoso', $searchRes['reply']);
        // Email and phone must be masked, no password or hash
        $this->assertStringNotContainsString('budi@example.com', $searchRes['reply']);
        $this->assertStringContainsString('b***i@example.com', $searchRes['reply']);
        $this->assertStringNotContainsString('password', strtolower($searchRes['reply']));
    }

    public function test_super_admin_can_query_account_linkage_to_store_and_courier(): void
    {
        // Query linkage for mitraOwner
        $linkRes = $this->service->chat($this->superAdmin, null, 'Akun mitra@example.com terhubung ke toko mana?');
        $this->assertFalse($linkRes['fallback']);
        $this->assertStringContainsString('Berkah Laundry', $linkRes['reply']);

        // Query linkage for courier
        $courierLinkRes = $this->service->chat($this->superAdmin, null, 'Akun Ahmad Kurir terhubung ke toko mana?');
        $this->assertFalse($courierLinkRes['fallback']);
        $this->assertStringContainsString('Berkah Laundry', $courierLinkRes['reply']);
    }

    public function test_super_admin_can_query_orders_awaiting_payment(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-WAITPAY-1',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'weight' => 2.0,
            'total_amount' => 45000,
            'total_price' => 45000,
            'order_date' => now(),
        ]);

        $res = $this->service->chat($this->superAdmin, null, 'Pesanan mana yang masih menunggu pembayaran?');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('ORD-WAITPAY-1', $res['reply']);
        $this->assertStringContainsString('Berkah Laundry', $res['reply']);
    }

    public function test_super_admin_can_query_completed_orders_with_null_completed_at(): void
    {
        $anomalyOrder = Order::create([
            'order_number' => 'ORD-ANOMALY-1',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'completed',
            'completed_at' => null,
            'weight' => 2.0,
            'total_amount' => 50000,
            'total_price' => 50000,
            'order_date' => now()->subDays(2),
        ]);

        $res = $this->service->chat($this->superAdmin, null, 'Tampilkan pesanan completed yang completed_at-nya kosong');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('ORD-ANOMALY-1', $res['reply']);
    }

    public function test_super_admin_can_query_orders_per_store_and_period(): void
    {
        Order::create([
            'order_number' => 'ORD-WEEK-1',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'completed',
            'completed_at' => now(),
            'weight' => 2.0,
            'total_amount' => 50000,
            'total_price' => 50000,
            'order_date' => now(),
        ]);

        $res = $this->service->chat($this->superAdmin, null, 'Berapa pesanan selesai minggu ini per toko?');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('Berkah Laundry', $res['reply']);
        $this->assertStringContainsString('1', $res['reply']);
    }

    public function test_super_admin_can_query_couriers_with_active_tasks(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TASK-1',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_in_progress',
            'weight' => 2.0,
            'total_amount' => 50000,
            'total_price' => 50000,
            'order_date' => now(),
        ]);

        CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => CourierTaskStatus::ACCEPTED->value,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $res = $this->service->chat($this->superAdmin, null, 'Kurir mana yang sedang punya tugas aktif?');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('Ahmad Kurir', $res['reply']);
        $this->assertStringContainsString('ORD-TASK-1', $res['reply']);
    }

    public function test_super_admin_can_query_pending_settlements(): void
    {
        SettlementPeriod::create([
            'reference' => 'SETTLE-TEST-001',
            'store_id' => $this->store->id,
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'interval' => 'weekly',
            'gross_amount' => 500000,
            'commission_amount' => 50000,
            'platform_fee_amount' => 5000,
            'net_amount' => 445000,
            'order_count' => 5,
            'status' => 'generated',
        ]);

        $res = $this->service->chat($this->superAdmin, null, 'Berapa settlement yang masih pending?');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('1', $res['reply']);
        $this->assertStringContainsString('Rp 445.000', $res['reply']);
    }

    public function test_super_admin_can_ask_for_admin_menu_explanation(): void
    {
        $res = $this->service->chat($this->superAdmin, null, 'Apa fungsi menu manajemen pengguna?');
        $this->assertFalse($res['fallback']);
        $this->assertStringContainsString('pengguna', strtolower($res['reply']));
        $this->assertStringContainsString('role', strtolower($res['reply']));
    }

    public function test_non_super_admin_roles_are_denied_super_admin_tools(): void
    {
        // 1. Customer calling platform tools in AiToolRegistry
        $customerOverview = $this->registry->execute('platform_get_overview', [], $this->customer);
        $this->assertArrayHasKey('error', $customerOverview);
        $this->assertStringContainsString('Akses ditolak', $customerOverview['error']);

        $customerUsers = $this->registry->execute('platform_get_user_stats', [], $this->customer);
        $this->assertArrayHasKey('error', $customerUsers);
        $this->assertStringContainsString('Akses ditolak', $customerUsers['error']);

        // 2. Mitra calling platform tools
        $mitraOverview = $this->registry->execute('platform_get_overview', [], $this->mitraOwner);
        $this->assertArrayHasKey('error', $mitraOverview);
        $this->assertStringContainsString('Akses ditolak', $mitraOverview['error']);

        // 3. Courier calling platform tools
        $courierOverview = $this->registry->execute('platform_get_overview', [], $this->courierUser);
        $this->assertArrayHasKey('error', $courierOverview);
        $this->assertStringContainsString('Akses ditolak', $courierOverview['error']);
    }

    public function test_mutation_requests_via_chat_are_strictly_rejected(): void
    {
        $res = $this->service->chat($this->superAdmin, null, 'Hapus pengguna Budi Santoso dari sistem sekarang');
        $this->assertFalse($res['fallback']);
        // Must reject mutation
        $this->assertTrue(
            str_contains(strtolower($res['reply']), 'tidak dapat') ||
            str_contains(strtolower($res['reply']), 'read-only') ||
            str_contains(strtolower($res['reply']), 'tidak diizinkan') ||
            str_contains(strtolower($res['reply']), 'melalui menu')
        );

        // Verify user was NOT deleted
        $this->assertDatabaseHas('users', ['id' => $this->customer->id, 'deleted_at' => null]);
    }

    public function test_super_admin_can_access_ai_chat_endpoint_via_admin_guard(): void
    {
        // Super admin logged in via guard 'admin'
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/ai/chat', [
                'message' => 'Halo, siapa saya?',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['conversation_id', 'reply', 'fallback']);
        $this->assertStringContainsString('Super Administrator', $response->json('reply'));
    }
}
