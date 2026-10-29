<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\OpenAiClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiRoleFunctionCallingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerA;
    protected User $customerB;
    protected User $mitraA;
    protected User $mitraB;
    protected User $courierA;
    protected User $courierB;
    protected User $superAdmin;

    protected Store $storeA;
    protected Store $storeB;

    protected Courier $courierRecordA;
    protected Courier $courierRecordB;

    protected AiAssistantService $service;
    protected OpenAiClient $client;
    protected AiToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Basic settings
        DB::table('settings')->updateOrInsert(['key' => 'COD_MAX_AMOUNT'], ['value' => '500000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'DISPUTE_WINDOW_HOURS'], ['value' => '24', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'PAYMENT_WINDOW_HOURS'], ['value' => '1', 'created_at' => now(), 'updated_at' => now()]);

        // Customer A & B
        $this->customerA = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@gmail.com',
            'phone' => '081234567890',
            'role' => 'customer',
        ]);
        $this->assignRole($this->customerA, 'customer');

        $this->customerB = User::factory()->create([
            'name' => 'Siti Rahma',
            'email' => 'siti.rahma@yahoo.com',
            'phone' => '081987654321',
            'role' => 'customer',
        ]);
        $this->assignRole($this->customerB, 'customer');

        // Mitra A (Store A) & Mitra B (Store B)
        $this->mitraA = User::factory()->create([
            'name' => 'Rian Wijaya',
            'email' => 'rian.mitra@laundryku.test',
            'phone' => '085712345678',
            'role' => 'store_admin',
        ]);
        $this->assignRole($this->mitraA, 'store_owner');

        $this->storeA = Store::factory()->create([
            'name' => 'Berkah Laundry',
            'owner_user_id' => $this->mitraA->id,
            'status' => 'approved',
            'is_active' => true,
            'city' => 'Jakarta Selatan',
            'address' => 'Jl. Tebet Raya No. 45',
            'average_rating' => 4.8,
            'review_count' => 12,
        ]);

        $this->mitraB = User::factory()->create([
            'name' => 'Dewi Lestari',
            'email' => 'dewi.mitra@laundryku.test',
            'phone' => '085888776655',
            'role' => 'store_admin',
        ]);
        $this->assignRole($this->mitraB, 'store_owner');

        $this->storeB = Store::factory()->create([
            'name' => 'Harum Laundry',
            'owner_user_id' => $this->mitraB->id,
            'status' => 'approved',
            'is_active' => true,
            'city' => 'Bandung',
            'address' => 'Jl. Dago No. 12',
            'average_rating' => 4.5,
            'review_count' => 8,
        ]);

        // Services & Prices
        $serviceCuci = Service::firstOrCreate(['name' => 'Cuci & Setrika (Kiloan)'], ['description' => 'Paket komplit wangi', 'is_active' => true]);
        $serviceDry = Service::firstOrCreate(['name' => 'Dry Cleaning'], ['description' => 'Khusus jas dan gaun', 'is_active' => true]);

        ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $serviceCuci->id,
            'price_per_unit' => 10000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $serviceDry->id,
            'price_per_unit' => 35000,
            'unit' => 'item',
            'is_active' => true,
        ]);

        // Courier A (Store A) & Courier B (Store B)
        $this->courierA = User::factory()->create([
            'name' => 'Ahmad Kurir',
            'email' => 'ahmad.kurir@laundryku.test',
            'phone' => '081344556677',
            'role' => 'courier',
        ]);
        $this->assignRole($this->courierA, 'courier');

        $this->courierRecordA = Courier::create([
            'user_id' => $this->courierA->id,
            'store_id' => $this->storeA->id,
            'vehicle_type' => 'Honda Vario',
            'plate_number' => 'B 1234 ABC',
            'is_available' => true,
            'status' => 'approved',
        ]);

        $this->courierB = User::factory()->create([
            'name' => 'Joko Kurir',
            'email' => 'joko.kurir@laundryku.test',
            'phone' => '081399887766',
            'role' => 'courier',
        ]);
        $this->assignRole($this->courierB, 'courier');

        $this->courierRecordB = Courier::create([
            'user_id' => $this->courierB->id,
            'store_id' => $this->storeB->id,
            'vehicle_type' => 'Yamaha NMAX',
            'plate_number' => 'D 5678 XYZ',
            'is_available' => false,
            'status' => 'approved',
        ]);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@laundryku.test',
            'phone' => '081122334455',
            'role' => 'super_admin',
        ]);
        $this->assignRole($this->superAdmin, 'super_admin');

        // Customer A's Orders
        $orderA1 = Order::factory()->create([
            'order_number' => 'ORD-CUST-101',
            'customer_id' => $this->customerA->id,
            'store_id' => $this->storeA->id,
            'status' => 'processing',
            'total_amount' => 50000,
            'weight' => 5.0,
            'actual_weight' => 5.0,
            'payment_status' => 'paid',
            'order_date' => now()->subDay(),
        ]);

        Payment::create([
            'order_id' => $orderA1->id,
            'amount' => 50000,
            'status' => 'paid',
            'payment_method' => 'qris',
            'paid_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'order_number' => 'ORD-CUST-102',
            'customer_id' => $this->customerA->id,
            'store_id' => $this->storeA->id,
            'status' => 'completed',
            'total_amount' => 30000,
            'weight' => 3.0,
            'actual_weight' => 3.0,
            'payment_status' => 'paid',
            'order_date' => now()->subDays(3),
            'completed_at' => now()->subDays(2),
        ]);

        // Customer B's Order
        Order::factory()->create([
            'order_number' => 'ORD-CUST-201',
            'customer_id' => $this->customerB->id,
            'store_id' => $this->storeB->id,
            'status' => 'completed',
            'total_amount' => 75000,
            'weight' => 7.5,
            'payment_status' => 'paid',
            'order_date' => now()->subDays(5),
            'completed_at' => now()->subDays(4),
        ]);

        // Store A Order
        Order::factory()->create([
            'order_number' => 'ORD-STORE-101',
            'customer_id' => $this->customerA->id,
            'store_id' => $this->storeA->id,
            'status' => 'created',
            'total_amount' => 40000,
            'weight' => 4.0,
            'payment_status' => 'unpaid',
            'order_date' => now(),
        ]);

        // Store B Order
        Order::factory()->create([
            'order_number' => 'ORD-STORE-201',
            'customer_id' => $this->customerB->id,
            'store_id' => $this->storeB->id,
            'status' => 'ready_for_delivery',
            'total_amount' => 65000,
            'weight' => 6.5,
            'payment_status' => 'paid',
            'order_date' => now()->subHours(5),
        ]);

        // Review on Store A
        Review::create([
            'customer_id' => $this->customerA->id,
            'store_id' => $this->storeA->id,
            'order_id' => $orderA1->id,
            'rating' => 5,
            'comment' => 'Cucian wangi, rapi, dan cepat selesai!',
        ]);

        // Dispute on Store A
        Dispute::create([
            'complaint_number' => 'CMP-TEST-001',
            'order_id' => $orderA1->id,
            'opened_by' => $this->customerA->id,
            'reason' => 'Ada kancing baju lepas saat dicuci',
            'description' => 'Kancing baju kemeja putih lepas saat proses cuci.',
            'status' => 'under_review',
        ]);

        // Courier A Task
        CourierTask::create([
            'courier_id' => $this->courierRecordA->id,
            'order_id' => $orderA1->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now()->subMinutes(45),
        ]);

        // Pending Store for platform approval
        Store::factory()->create([
            'owner_user_id' => $this->mitraB->id,
            'name' => 'Laundry Sejahtera Baru',
            'city' => 'Surabaya',
            'address' => 'Jl. Gubeng No. 10',
            'status' => 'submitted',
            'is_active' => false,
        ]);

        // Setup AI Service with OpenAiClient in local simulation mode
        config(['ai.mock_in_local' => true]);
        config(['ai.api_key' => '']); // trigger simulateLocalResponse

        $this->client = new OpenAiClient();
        $this->registry = new AiToolRegistry();
        $this->service = new AiAssistantService($this->client, $this->registry);
    }

    protected function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id]);
    }

    public function test_customer_role_10_questions_and_tenant_isolation(): void
    {
        $questions = [
            'siapa saya sebenarnya?',
            'bisa lihat daftar cucian saya yang aktif?',
            'bagaimana detail pesanan cucian saya ORD-CUST-101?',
            'berapa total pesanan dan pengeluaran saya sejauh ini?',
            'apakah pesanan ORD-CUST-101 sudah lunas dibayar?',
            'tampilkan ulasan yang pernah saya berikan',
            'cek riwayat komplain cucian yang pernah saya ajukan',
            'apa saja layanan dan harga di toko Berkah Laundry?',
            'alamat dan jam buka toko Berkah Laundry di mana ya?',
            'siapakah kamu?',
        ];

        foreach ($questions as $q) {
            $res = $this->service->chat($this->customerA, null, $q);
            $this->assertNotEmpty($res['reply'], "Pertanyaan '{$q}' harus menghasilkan jawaban.");
            $this->assertFalse($res['fallback'], "Pertanyaan '{$q}' harus ditangani oleh AI function calling.");

            // Anti-leak check: must not leak other customer's order ORD-CUST-201
            $this->assertStringNotContainsString('ORD-CUST-201', $res['reply']);
            $this->assertStringNotContainsString('Siti Rahma', $res['reply']);
        }

        // Cross-tenant test: Customer A requests Customer B's order ORD-CUST-201
        $crossRes = $this->service->chat($this->customerA, null, 'cek detail pesanan ORD-CUST-201');
        $this->assertTrue(
            str_contains($crossRes['reply'], 'tidak ditemukan') ||
            str_contains($crossRes['reply'], 'tidak diizinkan') ||
            str_contains($crossRes['reply'], 'bukan milik Anda')
        );
        $this->assertStringNotContainsString('75.000', $crossRes['reply']);
    }

    public function test_store_admin_role_10_questions_and_tenant_isolation(): void
    {
        $questions = [
            'siapa saya dan apa toko yang saya kelola?',
            'bagaimana ringkasan dan kondisi toko saya hari ini?',
            'berapa omzet dan performa toko saya hari ini?',
            'tampilkan daftar pesanan yang baru masuk ke toko saya',
            'bisa lihat rincian pesanan ORD-STORE-101 di toko saya?',
            'siapa saja kurir yang terhubung ke toko saya dan apakah siap tugas?',
            'apa saja daftar layanan aktif dan tarif kiloan di toko saya?',
            'bagaimana rating rata-rata dan ulasan terbaru pelanggan toko saya?',
            'apakah ada komplain pelanggan yang masuk ke toko saya?',
            'siapa anda?',
        ];

        foreach ($questions as $q) {
            $res = $this->service->chat($this->mitraA, null, $q);
            $this->assertNotEmpty($res['reply'], "Pertanyaan '{$q}' harus menghasilkan jawaban.");
            $this->assertFalse($res['fallback'], "Pertanyaan '{$q}' harus ditangani oleh AI function calling.");

            // Must contain Store A's data
            $this->assertStringNotContainsString('Harum Laundry', $res['reply']);
            $this->assertStringNotContainsString('ORD-STORE-201', $res['reply']);
        }

        // Cross-tenant test: Store A requests Store B's order ORD-STORE-201
        $crossRes = $this->service->chat($this->mitraA, null, 'cek pesanan ORD-STORE-201 di toko saya');
        $this->assertTrue(
            str_contains($crossRes['reply'], 'tidak ditemukan') ||
            str_contains($crossRes['reply'], 'tidak diizinkan')
        );
        $this->assertStringNotContainsString('65.000', $crossRes['reply']);
    }

    public function test_courier_role_10_questions_and_tenant_isolation(): void
    {
        $questions = [
            'siapa saya dan apa data akun saya?',
            'tampilkan profil kurir dan kendaraan saya',
            'apa saja tugas pengantaran atau penjemputan saya yang aktif?',
            'bagaimana statistik tugas dan performa saya hari ini?',
            'berapa tugas yang sudah berhasil saya selesaikan?',
            'bagaimana aturan penerimaan uang tunai COD untuk kurir?',
            'apa batas maksimal uang COD yang boleh saya terima?',
            'cek plat nomor dan status ketersediaan motor saya',
            'bisa beri tahu informasi toko tempat saya bertugas?',
            'kamu siapa?',
        ];

        foreach ($questions as $q) {
            $res = $this->service->chat($this->courierA, null, $q);
            $this->assertNotEmpty($res['reply'], "Pertanyaan '{$q}' harus menghasilkan jawaban.");
            $this->assertFalse($res['fallback'], "Pertanyaan '{$q}' harus ditangani oleh AI function calling.");

            // Anti-leak: Courier A cannot see Courier B's Yamaha NMAX / D 5678 XYZ
            $this->assertStringNotContainsString('Yamaha NMAX', $res['reply']);
            $this->assertStringNotContainsString('D 5678 XYZ', $res['reply']);
            $this->assertStringNotContainsString('Joko Kurir', $res['reply']);
        }
    }

    public function test_super_admin_role_10_questions_and_pii_masking(): void
    {
        $questions = [
            'siapa saya dan apa wewenang saya di sistem?',
            'bagaimana ringkasan platform LaundryKu secara keseluruhan?',
            'berapa total transaksi dan GMV platform hari ini?',
            'bagaimana statistik seluruh pengguna di platform saat ini?',
            'berapa jumlah pelanggan, mitra, dan kurir yang terdaftar?',
            'apakah ada pendaftaran toko mitra baru yang menunggu approval?',
            'tampilkan toko yang sedang menunggu verifikasi admin',
            'apakah ada sengketa atau komplain transaksi di platform?',
            'apa saja tarif layanan standar di ekosistem LaundryKu?',
            'siapa kamu dan apa kemampuanmu?',
        ];

        foreach ($questions as $q) {
            $res = $this->service->chat($this->superAdmin, null, $q);
            $this->assertNotEmpty($res['reply'], "Pertanyaan '{$q}' harus menghasilkan jawaban.");
            $this->assertFalse($res['fallback'], "Pertanyaan '{$q}' harus ditangani oleh AI function calling.");

            // PII protection: raw full email/phone/password must NOT leak
            $this->assertStringNotContainsString('password', strtolower($res['reply']));
            $this->assertStringNotContainsString('081234567890', $res['reply']); // masked
            $this->assertStringNotContainsString('budi.santoso@gmail.com', $res['reply']); // masked
        }
    }

    public function test_role_tool_isolation_definitions(): void
    {
        // Customer definitions
        $custDefs = $this->registry->definitionsForUser($this->customerA);
        $custToolNames = array_map(fn ($t) => $t['function']['name'], $custDefs);

        $this->assertContains('customer_list_orders', $custToolNames);
        $this->assertContains('customer_get_order_detail', $custToolNames);
        $this->assertNotContains('store_get_summary', $custToolNames, 'Customer tidak boleh memiliki tool store_get_summary');
        $this->assertNotContains('platform_get_overview', $custToolNames, 'Customer tidak boleh memiliki tool platform_get_overview');
        $this->assertNotContains('courier_get_tasks', $custToolNames, 'Customer tidak boleh memiliki tool courier_get_tasks');

        // Store Admin definitions
        $storeDefs = $this->registry->definitionsForUser($this->mitraA);
        $storeToolNames = array_map(fn ($t) => $t['function']['name'], $storeDefs);

        $this->assertContains('store_get_summary', $storeToolNames);
        $this->assertContains('store_list_orders', $storeToolNames);
        $this->assertNotContains('customer_list_orders', $storeToolNames, 'Store admin tidak boleh memiliki tool customer_list_orders');
        $this->assertNotContains('platform_get_overview', $storeToolNames, 'Store admin tidak boleh memiliki tool platform_get_overview');

        // Courier definitions
        $courierDefs = $this->registry->definitionsForUser($this->courierA);
        $courierToolNames = array_map(fn ($t) => $t['function']['name'], $courierDefs);

        $this->assertContains('courier_get_tasks', $courierToolNames);
        $this->assertContains('courier_get_profile', $courierToolNames);
        $this->assertNotContains('store_get_summary', $courierToolNames, 'Courier tidak boleh memiliki tool store_get_summary');
        $this->assertNotContains('platform_get_overview', $courierToolNames, 'Courier tidak boleh memiliki tool platform_get_overview');

        // Super Admin definitions
        $adminDefs = $this->registry->definitionsForUser($this->superAdmin);
        $adminToolNames = array_map(fn ($t) => $t['function']['name'], $adminDefs);

        $this->assertContains('platform_get_overview', $adminToolNames);
        $this->assertContains('platform_get_user_stats', $adminToolNames);
        $this->assertNotContains('store_list_orders', $adminToolNames);
        $this->assertNotContains('courier_get_tasks', $adminToolNames);
    }
}
