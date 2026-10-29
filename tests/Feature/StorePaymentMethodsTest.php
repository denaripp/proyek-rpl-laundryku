<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\StorePayoutAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorePaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $dbRole = match ($role) {
            'store_owner', 'store_admin' => User::ROLE_STORE_ADMIN,
            'courier' => User::ROLE_COURIER,
            'super_admin' => User::ROLE_SUPER_ADMIN,
            default => User::ROLE_CUSTOMER,
        };

        $user = User::factory()->create([
            'role' => $dbRole,
            'is_active' => true,
        ]);

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel->id);
        }

        if (in_array($role, ['store_owner', 'store_admin'], true)) {
            $storeAdminRole = Role::where('name', 'store_admin')->first();
            if ($storeAdminRole && ! $user->roles->contains($storeAdminRole->id)) {
                $user->roles()->attach($storeAdminRole->id);
            }
        }

        return $user;
    }

    private function makeApprovedStore(?User $owner = null): Store
    {
        $owner = $owner ?? $this->makeUser('store_admin');
        return Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'cod_enabled' => true,
            'qris_enabled' => false,
            'va_enabled' => false,
            'va_banks' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
            'service_radius_km' => 10,
            'latitude' => -6.2000,
            'longitude' => 106.8166,
        ]);
    }

    private function makeOrder(Store $store, User $customer, string $status = 'awaiting_payment', float $amount = 30000): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => $status,
            'payment_status' => 'unpaid',
            'weight' => 3.0,
            'estimated_weight' => 3.0,
            'actual_weight' => 3.0,
            'total_price' => $amount,
            'total_amount' => $amount,
            'final_price' => $amount,
            'pickup_address' => 'Jl. Test 123',
            'delivery_address' => 'Jl. Test 123',
            'order_date' => now(),
        ]);

        $service = Service::create(['name' => 'Cuci Komplit', 'is_active' => true]);
        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => 3.0,
            'unit' => 'kg',
            'price_per_unit' => $amount / 3.0,
            'total_price' => $amount,
        ]);

        return $order;
    }

    /**
     * Test 1: Hak akses mitra - pemilik toko bisa melihat dan mengubah metode pembayaran tokonya.
     */
    public function test_mitra_bisa_melihat_dan_mengubah_metode_pembayaran_toko_sendiri(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // GET payment methods
        $getRes = $this->getJson('/api/v1/store/payment-methods');
        $getRes->assertStatus(200);
        $this->assertTrue($getRes->json('data.cod_enabled'));
        $this->assertFalse($getRes->json('data.qris_enabled'));

        // PUT payment methods
        $putRes = $this->putJson('/api/v1/store/payment-methods', [
            'cod_enabled' => true,
            'qris_enabled' => true,
            'va_enabled' => true,
            'va_banks' => ['BCA', 'BNI'],
        ]);

        $putRes->assertStatus(200);
        $this->assertTrue($putRes->json('data.qris_enabled'));
        $this->assertTrue($putRes->json('data.va_enabled'));
        $this->assertEquals(['BCA', 'BNI'], $putRes->json('data.va_banks'));

        $store->refresh();
        $this->assertTrue($store->qris_enabled);
        $this->assertTrue($store->va_enabled);
        $this->assertEquals(['BCA', 'BNI'], $store->va_banks);

        // Pastikan AuditLog tercatat
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $owner->id,
            'action' => 'STORE_PAYMENT_METHODS_UPDATE',
            'resource_id' => $store->id,
        ]);
    }

    /**
     * Test 2: Hak akses mitra - mitra toko lain atau pelanggan ditolak mengubah metode pembayaran.
     */
    public function test_mitra_toko_lain_atau_bukan_mitra_ditolak_mengubah_metode_pembayaran(): void
    {
        $ownerA = $this->makeUser('store_admin');
        $storeA = $this->makeApprovedStore($ownerA);

        $ownerB = $this->makeUser('store_admin');
        $this->makeApprovedStore($ownerB);

        $customer = $this->makeUser('customer');

        // Mitra B mencoba mengubah Toko A dengan passing query store_id Toko A
        Sanctum::actingAs($ownerB, ['role:store_admin']);
        $resMitraB = $this->putJson('/api/v1/store/payment-methods?store_id=' . $storeA->id, [
            'cod_enabled' => true,
            'qris_enabled' => true,
            'va_enabled' => false,
        ]);
        // Mitra B hanya bisa resolve toko miliknya sendiri, toko A tidak akan diubah
        $storeA->refresh();
        $this->assertFalse($storeA->qris_enabled);

        // Customer biasa ditolak akses route store-admin
        Sanctum::actingAs($customer, ['role:customer']);
        $resCustomer = $this->putJson('/api/v1/store/payment-methods', [
            'cod_enabled' => true,
            'qris_enabled' => true,
            'va_enabled' => false,
        ]);
        $this->assertEquals(403, $resCustomer->status());
    }

    /**
     * Test 3: Minimal satu metode pembayaran harus aktif (ditolak jika semua dinonaktifkan).
     */
    public function test_minimal_satu_metode_pembayaran_harus_aktif(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);

        Sanctum::actingAs($owner, ['role:store_admin']);
        $response = $this->putJson('/api/v1/store/payment-methods', [
            'cod_enabled' => false,
            'qris_enabled' => false,
            'va_enabled' => false,
        ]);

        $response->assertStatus(422);
        $this->assertEquals('Minimal satu metode pembayaran harus aktif.', $response->json('message'));

        $store->refresh();
        $this->assertTrue($store->cod_enabled); // Tidak berubah
    }

    /**
     * Test 4: Metode tidak aktif ditolak saat pelanggan memilih metode pembayaran.
     */
    public function test_metode_tidak_aktif_ditolak_saat_pelanggan_memilih_pembayaran(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $store->update([
            'cod_enabled' => true,
            'qris_enabled' => false,
            'va_enabled' => true,
            'va_banks' => ['BCA', 'BNI'],
        ]);

        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($store, $customer);

        Sanctum::actingAs($customer, ['role:customer']);

        // 1. Toko menonaktifkan QRIS -> Pelanggan bayar QRIS ditolak 422
        $qrisRes = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'qris',
        ]);
        $qrisRes->assertStatus(422);
        $this->assertStringContainsString('QRIS tidak aktif', $qrisRes->json('message'));

        // 2. Bank BRI tidak ada di daftar va_banks toko -> Pelanggan bayar VA BRI ditolak 422
        $vaBriRes = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'va',
            'bank_code' => 'BRI',
        ]);
        $vaBriRes->assertStatus(422);
        $this->assertStringContainsString('BRI tidak aktif', $vaBriRes->json('message'));

        // 3. Toko menonaktifkan COD -> Pelanggan bayar COD ditolak 422
        $store->update(['cod_enabled' => false, 'qris_enabled' => true]);
        $codRes = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'cod',
        ]);
        $codRes->assertStatus(422);
        $this->assertStringContainsString('COD tidak aktif', $codRes->json('message'));
    }

    /**
     * Test 5: Jika kunci gateway belum ada di .env, tampilkan "metode belum tersedia", bukan 500.
     */
    public function test_kunci_gateway_belum_ada_tampilkan_metode_belum_tersedia_bukan_500(): void
    {
        config(['xendit.api_key' => null]);

        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $store->update(['qris_enabled' => true, 'va_enabled' => true]);

        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($store, $customer);

        Sanctum::actingAs($customer, ['role:customer']);
        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'qris',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Metode belum tersedia', $response->json('message'));
        $this->assertEquals('GATEWAY_UNAVAILABLE', $response->json('error.code'));
    }

    /**
     * Test 6: Nominal pembayaran selalu dari server dan tidak bisa dimanipulasi oleh klien.
     */
    public function test_nominal_pembayaran_selalu_dari_server_dan_tidak_bisa_dimanipulasi(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');

        // Order total asli adalah Rp 45.000
        $order = $this->makeOrder($store, $customer, 'awaiting_payment', 45000);

        Sanctum::actingAs($customer, ['role:customer']);
        // Klien mencoba mengirim nominal palsu Rp 1.000
        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'cod',
            'amount' => 1000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(45000, $response->json('data.amount'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 45000,
        ]);
    }

    /**
     * Test 7: Order QRIS/VA tidak bisa ke processing sebelum PAID.
     */
    public function test_order_qris_va_tidak_bisa_ke_processing_sebelum_paid(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');

        $order = $this->makeOrder($store, $customer, 'awaiting_payment', 30000);
        $order->payment_method = 'qris';
        $order->save();

        Sanctum::actingAs($owner, ['role:store_admin']);
        // Mitra mencoba transisi ke processing
        $response = $this->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'processing',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid transition from awaiting_payment to processing', $response->json('message'));

        $order->refresh();
        $this->assertEquals('awaiting_payment', $order->status);
    }

    /**
     * Test 8: Order COD bisa ke processing sebelum PAID, dan PAID baru saat konfirmasi COD.
     */
    public function test_order_cod_bisa_ke_processing_sebelum_paid_dan_paid_setelah_konfirmasi(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');

        $order = $this->makeOrder($store, $customer, 'awaiting_payment', 35000);

        // Pelanggan memilih metode COD
        Sanctum::actingAs($customer, ['role:customer']);
        $payRes = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'cod',
        ]);
        $payRes->assertStatus(200);

        $order->refresh();
        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('pending', $order->payment_status);

        // Mitra toko memproses cucian (awaiting_payment -> processing TANPA PAID)
        Sanctum::actingAs($owner, ['role:store_admin']);
        $processRes = $this->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'processing',
        ]);
        $processRes->assertStatus(200);

        $order->refresh();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('pending', $order->payment_status); // Tetap pending

        // Konfirmasi COD oleh toko
        $confirmRes = $this->postJson("/api/v1/store/orders/{$order->id}/confirm-cod");
        $confirmRes->assertStatus(200);
        $this->assertEquals('PAID', $confirmRes->json('data.payment_status'));

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
    }

    /**
     * Test 9: Webhook Xendit QRIS/VA - token benar, salah, kosong, config belum diisi, dan ganda (idempoten).
     */
    public function test_webhook_xendit_token_benar_salah_kosong_dan_ganda(): void
    {
        $callbackToken = 'test-xendit-callback-token-secret-xyz';
        config([
            'xendit.webhook_secret' => $callbackToken,
        ]);

        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($store, $customer, 'awaiting_payment', 50000);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'qris',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_test_999',
            'expires_at' => now()->addHour(),
        ]);

        $payloadData = [
            'id' => 'inv_test_999',
            'event_id' => 'evt_test_1',
            'event' => 'invoice.paid',
            'status' => 'PAID',
            'amount' => 50000,
            'payment_id' => 'pay_123',
            'payment_method' => 'QRIS',
            'payment_channel' => 'QRIS',
        ];

        // 1. Token SALAH -> 401
        $badTokenRes = $this->withHeaders([
            'x-callback-token' => 'invalid-token-123',
        ])->postJson('/api/webhooks/xendit', $payloadData);
        $badTokenRes->assertStatus(401);

        // 2. Token KOSONG -> 401
        $emptyTokenRes = $this->postJson('/api/webhooks/xendit', $payloadData);
        $emptyTokenRes->assertStatus(401);

        // 3. Token config belum diisi -> 401
        config(['xendit.webhook_secret' => null]);
        $noConfigRes = $this->withHeaders([
            'x-callback-token' => $callbackToken,
        ])->postJson('/api/webhooks/xendit', $payloadData);
        $noConfigRes->assertStatus(401);

        // Kembalikan token config yang valid
        config(['xendit.webhook_secret' => $callbackToken]);

        // 4. Token BENAR -> 200, status order dan payment menjadi paid
        $validRes = $this->withHeaders([
            'x-callback-token' => $callbackToken,
        ])->postJson('/api/webhooks/xendit', $payloadData);
        $validRes->assertStatus(200);

        $payment->refresh();
        $order->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals('paid', $order->payment_status);

        // 5. Webhook GANDA (duplikat event yang sama) -> idempoten, return 200 tanpa error
        $dupRes = $this->withHeaders([
            'x-callback-token' => $callbackToken,
        ])->postJson('/api/webhooks/xendit', $payloadData);
        $dupRes->assertStatus(200);

        // Event tercatat hanya sekali
        $this->assertDatabaseCount('payment_events', 1);
    }

    /**
     * Test 10: GET /api/store/payout-account mengembalikan null jika belum ada rekening.
     */
    public function test_get_payout_account_belum_ada_mengembalikan_null(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $res = $this->getJson('/api/v1/store/payout-account');
        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * Test 11: Pemilik toko bisa menyimpan dan mengupdate (upsert) rekening pencairan.
     */
    public function test_pemilik_toko_bisa_menyimpan_dan_mengupdate_rekening_pencairan(): void
    {
        $owner = $this->makeUser('store_admin');
        $store = $this->makeApprovedStore($owner);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // 1. Simpan pertama kali
        $putRes = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'budi santoso',
        ]);

        $putRes->assertStatus(200);
        $putRes->assertJson([
            'success' => true,
            'message' => 'Rekening pencairan dana berhasil disimpan.',
            'data' => [
                'store_id' => (string) $store->id,
                'bank_code' => 'BCA',
                'account_number' => '1234567890',
                'account_number_masked' => '****7890',
                'account_holder_name' => 'BUDI SANTOSO',
            ],
        ]);

        $this->assertDatabaseHas('store_payout_accounts', [
            'store_id' => $store->id,
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'BUDI SANTOSO',
        ]);

        // 2. GET mengembalikan data tersimpan
        $getRes = $this->getJson('/api/v1/store/payout-account');
        $getRes->assertStatus(200);
        $this->assertEquals('****7890', $getRes->json('data.account_number_masked'));
        $this->assertEquals('BUDI SANTOSO', $getRes->json('data.account_holder_name'));

        // 3. Update rekening (upsert)
        $updateRes = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'MANDIRI',
            'account_number' => '987654321012',
            'account_holder_name' => 'budi santoso putra',
        ]);

        $updateRes->assertStatus(200);
        $this->assertEquals('MANDIRI', $updateRes->json('data.bank_code'));
        $this->assertEquals('****1012', $updateRes->json('data.account_number_masked'));
        $this->assertEquals('BUDI SANTOSO PUTRA', $updateRes->json('data.account_holder_name'));

        // Pastikan hanya ada 1 baris untuk toko ini (unique constraint store_id)
        $this->assertDatabaseCount('store_payout_accounts', 1);
        $this->assertDatabaseHas('store_payout_accounts', [
            'store_id' => $store->id,
            'bank_code' => 'MANDIRI',
            'account_number' => '987654321012',
        ]);
    }

    /**
     * Test 12: Bukan pemilik toko ditolak (401/403).
     */
    public function test_bukan_pemilik_toko_ditolak_mengakses_rekening_pencairan(): void
    {
        $owner = $this->makeUser('store_admin');
        $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');

        // Unauthenticated -> 401
        $this->getJson('/api/v1/store/payout-account')->assertStatus(401);
        $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'BUDI',
        ])->assertStatus(401);

        // Customer biasa -> 403
        Sanctum::actingAs($customer, ['role:customer']);
        $this->getJson('/api/v1/store/payout-account')->assertStatus(403);
        $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'BUDI',
        ])->assertStatus(403);
    }

    /**
     * Test 13: store_id diambil dari session/auth toko, bukan dari body request.
     */
    public function test_store_id_diambil_dari_session_bukan_body_request(): void
    {
        $ownerA = $this->makeUser('store_admin');
        $storeA = $this->makeApprovedStore($ownerA);

        $ownerB = $this->makeUser('store_admin');
        $storeB = $this->makeApprovedStore($ownerB);

        Sanctum::actingAs($ownerA, ['role:store_admin']);

        // Owner A mencoba menyisipkan store_id milik Store B di body
        $res = $this->putJson('/api/v1/store/payout-account', [
            'store_id' => $storeB->id,
            'bank_code' => 'BRI',
            'account_number' => '555566667777',
            'account_holder_name' => 'REKENING TOKO A',
        ]);

        $res->assertStatus(200);
        // Yang terupdate adalah Store A
        $this->assertEquals((string) $storeA->id, $res->json('data.store_id'));

        $this->assertDatabaseHas('store_payout_accounts', [
            'store_id' => $storeA->id,
            'account_number' => '555566667777',
        ]);

        // Store B sama sekali tidak memiliki rekening
        $this->assertDatabaseMissing('store_payout_accounts', [
            'store_id' => $storeB->id,
        ]);
    }

    /**
     * Test 14: Validasi input rekening pencairan (bank, nomor rekening 8-20 digit, nama pemilik).
     */
    public function test_validasi_input_rekening_pencairan(): void
    {
        $owner = $this->makeUser('store_admin');
        $this->makeApprovedStore($owner);

        Sanctum::actingAs($owner, ['role:store_admin']);

        // 1. Bank tidak valid
        $resBadBank = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BANK_GAIB',
            'account_number' => '1234567890',
            'account_holder_name' => 'BUDI',
        ]);
        $resBadBank->assertStatus(422);

        // 2. Nomor rekening terlalu pendek (< 8 digit)
        $resShortAcc = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567',
            'account_holder_name' => 'BUDI',
        ]);
        $resShortAcc->assertStatus(422);

        // 3. Nomor rekening non-angka
        $resNonNumAcc = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890abc',
            'account_holder_name' => 'BUDI',
        ]);
        $resNonNumAcc->assertStatus(422);

        // 4. Nama pemilik kosong
        $resEmptyName = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => '',
        ]);
        $resEmptyName->assertStatus(422);

        // 5. Nama pemilik melebihi 100 karakter
        $resLongName = $this->putJson('/api/v1/store/payout-account', [
            'bank_code' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => str_repeat('A', 101),
        ]);
        $resLongName->assertStatus(422);
    }
}
