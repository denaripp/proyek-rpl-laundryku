<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreServiceOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $roleModel = \App\Models\Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel);
        }
        return $user;
    }

    private function makeApprovedStore(): Store
    {
        $owner = $this->makeUser('store_owner');
        return Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
            'latitude' => -6.2000,
            'longitude' => 106.8166,
        ]);
    }

    private function makeServicePrice(Store $store, ?Service $service = null): ServicePrice
    {
        $service = $service ?? Service::create([
            'name' => 'Service ' . uniqid(),
            'is_active' => true,
        ]);

        return ServicePrice::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'price_per_unit' => 10000,
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Layanan valid milik toko berhasil dipesan
     * (menggunakan service_price id seperti yang dikirim frontend /stores/[id]/order?service=[id]).
     */
    public function test_layanan_valid_berhasil_dipesan(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [
                [
                    'service_id' => $sp->id,
                    'quantity' => 3,
                ],
            ],
            'pickup_address' => 'Jl. Valid Pickup 123',
            'pickup_latitude' => -6.2010,
            'pickup_longitude' => 106.8170,
            'delivery_address' => 'Jl. Valid Delivery 123',
            'delivery_latitude' => -6.2020,
            'delivery_longitude' => 106.8180,
            'customer_notes' => 'Pesanan layanan valid',
        ];

        $response = $this->actingAs($customer)->postJson('/api/orders', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'created',
        ]);
        $this->assertDatabaseHas('order_items', [
            'service_id' => $sp->service_id,
            'quantity' => 3,
        ]);
    }

    /**
     * Test 2: Layanan milik toko lain ditolak dengan error validasi items.*.service_id.
     */
    public function test_layanan_milik_toko_lain_ditolak(): void
    {
        $customer = $this->makeUser('customer');
        $storeA = $this->makeApprovedStore();
        $storeB = $this->makeApprovedStore();

        // ServicePrice ini dimiliki oleh Toko B
        $spB = $this->makeServicePrice($storeB);

        // Pelanggan memesan ke Toko A tetapi mengirimkan service_id milik Toko B
        $payload = [
            'store_id' => $storeA->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [
                [
                    'service_id' => $spB->id,
                    'quantity' => 3,
                ],
            ],
            'pickup_address' => 'Jl. Cross Store 123',
            'pickup_latitude' => -6.2010,
            'pickup_longitude' => 106.8170,
            'delivery_address' => 'Jl. Cross Store 123',
            'delivery_latitude' => -6.2020,
            'delivery_longitude' => 106.8180,
        ];

        $response = $this->actingAs($customer)->postJson('/api/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.service_id']);
        $errors = $response->json('errors');
        $this->assertEquals(
            'The selected items.0.service_id is invalid.',
            $errors['items.0.service_id'][0]
        );
    }

    /**
     * Test 3: Buat pesanan dengan alamat (snapshot), lalu detail pesanan
     * untuk pelanggan dan untuk toko mengembalikan alamat tersebut.
     */
    public function test_buat_pesanan_dengan_alamat_dan_tampil_di_pelanggan_dan_toko(): void
    {
        $customer = $this->makeUser('customer');
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
            'latitude' => -6.2000,
            'longitude' => 106.8166,
        ]);
        $sp = $this->makeServicePrice($store);

        $addressSnapshot = [
            'label' => 'Rumah',
            'address_text' => 'Jl. Perjuangan 10b, Majasem, Cirebon',
            'latitude' => -6.7392743,
            'longitude' => 108.5238416,
            'recipient_name' => 'Muhammad Najwa Syarif',
            'recipient_phone' => '08123456789',
        ];

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'estimated_price' => 30000,
            'items' => [
                [
                    'service_id' => $sp->id,
                    'quantity' => 3,
                ],
            ],
            'pickup_address' => $addressSnapshot,
            'delivery_address' => $addressSnapshot,
            'customer_notes' => 'Tolong hati-hati dengan pakaian sutra',
        ];

        // 1. Pelanggan membuat pesanan
        $createRes = $this->actingAs($customer)->postJson('/api/orders', $payload);
        $createRes->assertStatus(201);
        $orderId = $createRes->json('data.id') ?? $createRes->json('id');

        // Pastikan tersimpan di database
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'pickup_address' => 'Jl. Perjuangan 10b, Majasem, Cirebon',
        ]);

        // 2. Detail pesanan untuk pelanggan mengembalikan alamat
        $customerDetailRes = $this->actingAs($customer)->getJson("/api/orders/{$orderId}");
        $customerDetailRes->assertStatus(200);
        $this->assertEquals(
            'Jl. Perjuangan 10b, Majasem, Cirebon',
            $customerDetailRes->json('data.pickup_address.address_text')
        );
        $this->assertEquals(
            'Muhammad Najwa Syarif',
            $customerDetailRes->json('data.pickup_address.recipient_name')
        );

        // 3. Detail pesanan untuk toko mengembalikan alamat yang sama
        $storeDetailRes = $this->actingAs($owner)->getJson("/api/orders/{$orderId}");
        $storeDetailRes->assertStatus(200);
        $this->assertEquals(
            'Jl. Perjuangan 10b, Majasem, Cirebon',
            $storeDetailRes->json('data.pickup_address.address_text')
        );
        $this->assertEquals(
            'Muhammad Najwa Syarif',
            $storeDetailRes->json('data.pickup_address.recipient_name')
        );
    }

    /**
     * Test 4: Pesanan lama yang alamatnya kosong mengembalikan "Alamat tidak tersedia".
     */
    public function test_pesanan_lama_alamat_kosong_mengembalikan_alamat_tidak_tersedia(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();

        // Pesanan lama tanpa kolom alamat terisi
        $oldOrder = \App\Models\Order::create([
            'order_number' => 'ORD-OLD-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'created',
            'weight' => 2,
            'total_price' => 20000,
            'total_amount' => 20000,
            'order_date' => now(),
            'pickup_address' => null,
            'delivery_address' => null,
        ]);

        $res = $this->actingAs($customer)->getJson("/api/orders/{$oldOrder->id}");
        $res->assertStatus(200);
        $this->assertEquals('Alamat tidak tersedia', $res->json('data.pickup_address.address_text'));
        $this->assertEquals('Alamat tidak tersedia', $res->json('data.delivery_address.address_text'));
    }

    /**
     * Test 5: Perhitungan harga service dari service_prices:
     * 2 kg x 7.000 = subtotal 14.000 (total 15.000 dengan platform fee)
     * 3 kg x 7.000 = subtotal 21.000 (total 22.000 dengan platform fee)
     */
    public function test_perhitungan_subtotal_dan_total_harga_service(): void
    {
        $customer = $this->makeUser('customer');
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
            'latitude' => -6.2000,
            'longitude' => 106.8166,
        ]);

        $service = Service::create([
            'name' => 'Cuci Kering',
            'is_active' => true,
        ]);

        $sp = ServicePrice::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'price_per_unit' => 7000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        // Case 1: 2 kg x 7.000 = 14.000 (+ platform fee 1.000 = 15.000)
        $payload2kg = [
            'store_id' => $store->id,
            'estimated_weight' => 2,
            'items' => [
                [
                    'service_id' => $sp->id,
                    'quantity' => 2,
                ],
            ],
            'pickup_address' => 'Jl. Test 2kg',
            'pickup_latitude' => -6.2010,
            'pickup_longitude' => 106.8170,
            'delivery_address' => 'Jl. Test 2kg',
            'delivery_latitude' => -6.2020,
            'delivery_longitude' => 106.8180,
        ];

        $res2kg = $this->actingAs($customer)->postJson('/api/orders', $payload2kg);
        $res2kg->assertStatus(201);
        $order2kgId = $res2kg->json('data.id') ?? $res2kg->json('id');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order2kgId,
            'service_id' => $service->id,
            'quantity' => 2,
            'price_per_unit' => 7000,
            'total_price' => 14000,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order2kgId,
            'estimated_price' => 15000,
            'total_amount' => 15000,
            'platform_fee' => 1000,
        ]);

        // Verifikasi detail endpoint untuk 2 kg
        $detailRes2kg = $this->actingAs($customer)->getJson("/api/orders/{$order2kgId}");
        $detailRes2kg->assertStatus(200);
        $this->assertEquals(14000, $detailRes2kg->json('data.items.0.line_estimated_amount'));
        $this->assertEquals(14000, $detailRes2kg->json('data.items.0.subtotal'));
        $this->assertEquals(7000, $detailRes2kg->json('data.items.0.unit_price'));
        $this->assertEquals(2, $detailRes2kg->json('data.items.0.quantity_estimated'));
        $this->assertEquals(15000, $detailRes2kg->json('data.estimated_amount'));
        $this->assertEquals(1000, $detailRes2kg->json('data.platform_fee'));

        // Case 2: 3 kg x 7.000 = 21.000 (+ platform fee 1.000 = 22.000)
        $payload3kg = [
            'store_id' => $store->id,
            'estimated_weight' => 3,
            'items' => [
                [
                    'service_id' => $sp->id,
                    'quantity' => 3,
                ],
            ],
            'pickup_address' => 'Jl. Test 3kg',
            'pickup_latitude' => -6.2010,
            'pickup_longitude' => 106.8170,
            'delivery_address' => 'Jl. Test 3kg',
            'delivery_latitude' => -6.2020,
            'delivery_longitude' => 106.8180,
        ];

        $res3kg = $this->actingAs($customer)->postJson('/api/orders', $payload3kg);
        $res3kg->assertStatus(201);
        $order3kgId = $res3kg->json('data.id') ?? $res3kg->json('id');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order3kgId,
            'service_id' => $service->id,
            'quantity' => 3,
            'price_per_unit' => 7000,
            'total_price' => 21000,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order3kgId,
            'estimated_price' => 22000,
            'total_amount' => 22000,
            'platform_fee' => 1000,
        ]);

        // Verifikasi detail endpoint untuk 3 kg dari sisi store owner
        $detailRes3kg = $this->actingAs($owner)->getJson("/api/orders/{$order3kgId}");
        $detailRes3kg->assertStatus(200);
        $this->assertEquals(21000, $detailRes3kg->json('data.items.0.line_estimated_amount'));
        $this->assertEquals(21000, $detailRes3kg->json('data.items.0.subtotal'));
        $this->assertEquals(7000, $detailRes3kg->json('data.items.0.unit_price'));
        $this->assertEquals(3, $detailRes3kg->json('data.items.0.quantity_estimated'));
        $this->assertEquals(22000, $detailRes3kg->json('data.estimated_amount'));
        $this->assertEquals(1000, $detailRes3kg->json('data.platform_fee'));
    }

    /**
     * Test: Request publik dari klien yang membuat pesanan tanpa item (atau items kosong) wajib ditolak.
     */
    public function test_pesanan_tanpa_item_dari_klien_ditolak(): void
    {
        $store = $this->makeApprovedStore();
        $customer = $this->makeUser('customer');

        // 1. Klien mengirim items array kosong dengan estimasi harga buatan sendiri
        $payloadEmptyItems = [
            'store_id' => $store->id,
            'items' => [],
            'estimated_price' => 50000,
            'total_amount' => 50000,
            'pickup_address' => 'Jl. Tanpa Item No. 1',
            'pickup_latitude' => -6.2001,
            'pickup_longitude' => 106.8167,
            'delivery_address' => 'Jl. Tanpa Item No. 1',
            'delivery_latitude' => -6.2001,
            'delivery_longitude' => 106.8167,
        ];

        $resEmpty = $this->actingAs($customer)->postJson('/api/orders', $payloadEmptyItems);
        $resEmpty->assertStatus(422);
        $resEmpty->assertJsonValidationErrors(['items']);

        // 2. Klien tidak mengirim field items sama sekali
        $payloadNoItems = [
            'store_id' => $store->id,
            'estimated_price' => 50000,
            'pickup_address' => 'Jl. Tanpa Item No. 2',
            'pickup_latitude' => -6.2001,
            'pickup_longitude' => 106.8167,
            'delivery_address' => 'Jl. Tanpa Item No. 2',
            'delivery_latitude' => -6.2001,
            'delivery_longitude' => 106.8167,
        ];

        $resMissing = $this->actingAs($customer)->postJson('/api/orders', $payloadNoItems);
        $resMissing->assertStatus(422);
        $resMissing->assertJsonValidationErrors(['items']);

        // 3. Klien mencoba bypass dengan menyisipkan is_internal=1 dan items kosong
        $payloadWithInternalBypass = [
            'store_id' => $store->id,
            'items' => [],
            'is_internal' => 1,
            'estimated_price' => 50000,
            'total_amount' => 50000,
            'pickup_address' => 'Jl. Bypass Internal No. 3',
            'pickup_latitude' => -6.2001,
            'pickup_longitude' => 106.8167,
            'delivery_address' => 'Jl. Bypass Internal No. 3',
            'delivery_latitude' => -6.2001,
            'delivery_longitude' => 106.8167,
        ];

        $resBypass = $this->actingAs($customer)->postJson('/api/orders', $payloadWithInternalBypass);
        $resBypass->assertStatus(422);
        $resBypass->assertJsonValidationErrors(['items']);

        // Pastikan tidak ada order yang terbuat di database
        $this->assertDatabaseMissing('orders', [
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Test: POST dengan is_internal=1 dan items kosong tetap ditolak 422.
     */
    public function test_post_dengan_is_internal_dan_items_kosong_tetap_ditolak_422(): void
    {
        $store = $this->makeApprovedStore();
        $customer = $this->makeUser('customer');

        $payload = [
            'store_id' => $store->id,
            'items' => [],
            'is_internal' => 1,
            'internal_order' => true,
            'estimated_price' => 50000,
            'total_amount' => 50000,
            'pickup_address' => 'Jl. Bypass Test No. 99',
            'pickup_latitude' => -6.2001,
            'pickup_longitude' => 106.8167,
            'delivery_address' => 'Jl. Bypass Test No. 99',
            'delivery_latitude' => -6.2001,
            'delivery_longitude' => 106.8167,
        ];

        $response = $this->actingAs($customer)->postJson('/api/v1/orders', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);

        $this->assertDatabaseMissing('orders', [
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Test: Mitra pemilik toko berhasil transisi order dari picked_up ke at_store (200).
     */
    public function test_mitra_pemilik_toko_picked_up_ke_at_store_berhasil_200(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        // Salinan order berstatus picked_up milik toko mitra
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'picked_up',
            'weight' => 3,
            'total_price' => 21000,
            'total_amount' => 22000,
            'order_date' => now(),
            'pickup_address' => 'Jl. Jemput No. 1',
            'delivery_address' => 'Jl. Antar No. 1',
        ]);

        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'AT_STORE',
            'note' => 'Cucian telah tiba di outlet laundry.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'at_store',
        ]);
    }

    /**
     * Test: Mitra toko lain ditolak saat mencoba transisi order toko lain (403).
     */
    public function test_mitra_toko_lain_ditolak_mengubah_status_pesanan(): void
    {
        $ownerA = $this->makeUser('store_owner');
        $storeA = Store::factory()->create([
            'owner_user_id' => $ownerA->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $ownerB = $this->makeUser('store_owner');
        $storeB = Store::factory()->create([
            'owner_user_id' => $ownerB->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $customer = $this->makeUser('customer');

        $orderA = \App\Models\Order::create([
            'order_number' => 'ORD-A-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $storeA->id,
            'status' => 'picked_up',
            'weight' => 2,
            'total_price' => 14000,
            'total_amount' => 15000,
            'order_date' => now(),
        ]);

        // Mitra toko B mencoba update order toko A
        $response = $this->actingAs($ownerB)->postJson("/api/v1/orders/{$orderA->id}/transition", [
            'to_status' => 'AT_STORE',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test: Transisi status yang tidak valid mengembalikan 422 bukan 500.
     */
    public function test_transisi_status_tidak_valid_mengembalikan_422_bukan_500(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-INVALID-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'picked_up',
            'weight' => 2,
            'total_price' => 14000,
            'total_amount' => 15000,
            'order_date' => now(),
        ]);

        // Transisi tidak valid: picked_up langsung ke completed (melewati at_store, weighed, processing, dll)
        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'COMPLETED',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TRANSITION',
            ],
        ]);
        $this->assertStringContainsString('Invalid transition', $response->json('message'));
    }

    /**
     * Test: Mitra pemilik toko berhasil transisi order dari weighed ke awaiting_price_approval (200).
     */
    public function test_mitra_pemilik_toko_weighed_ke_awaiting_price_approval_berhasil_200(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        // Salinan order berstatus weighed milik toko mitra
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-WEIGHED-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'weighed',
            'weight' => 4.5,
            'total_price' => 31500,
            'total_amount' => 32500,
            'order_date' => now(),
            'pickup_address' => 'Jl. Jemput No. 28',
            'delivery_address' => 'Jl. Antar No. 28',
        ]);

        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'AWAITING_PRICE_APPROVAL',
            'note' => 'Menunggu persetujuan harga final dari pelanggan.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_price_approval',
        ]);
    }

    /**
     * Test: Mitra toko lain ditolak saat mencoba transisi order toko lain dari weighed ke awaiting_price_approval (403).
     */
    public function test_mitra_toko_lain_ditolak_mengubah_status_weighed_ke_awaiting_price_approval(): void
    {
        $ownerA = $this->makeUser('store_owner');
        $storeA = Store::factory()->create([
            'owner_user_id' => $ownerA->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $ownerB = $this->makeUser('store_owner');
        $storeB = Store::factory()->create([
            'owner_user_id' => $ownerB->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $customer = $this->makeUser('customer');

        $orderA = \App\Models\Order::create([
            'order_number' => 'ORD-WEIGHED-A-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $storeA->id,
            'status' => 'weighed',
            'weight' => 4.0,
            'total_price' => 28000,
            'total_amount' => 29000,
            'order_date' => now(),
        ]);

        // Mitra toko B mencoba update order toko A
        $response = $this->actingAs($ownerB)->postJson("/api/v1/orders/{$orderA->id}/transition", [
            'to_status' => 'AWAITING_PRICE_APPROVAL',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', [
            'id' => $orderA->id,
            'status' => 'weighed',
        ]);
    }

    /**
     * Test: Transisi di luar tabel dari status weighed tetap mengembalikan 422.
     */
    public function test_transisi_di_luar_tabel_dari_weighed_tetap_422(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-WEIGHED-INVALID-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'weighed',
            'weight' => 3.5,
            'total_price' => 24500,
            'total_amount' => 25500,
            'order_date' => now(),
        ]);

        // Transisi tidak valid: weighed langsung ke completed (melewati persetujuan harga, pembayaran, dan pencucian)
        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'COMPLETED',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TRANSITION',
            ],
        ]);
        $this->assertStringContainsString('Invalid transition', $response->json('message'));
    }

    /**
     * Test: Mitra TIDAK BISA set price_approved lewat endpoint transition (hanya pelanggan pemilik).
     */
    public function test_mitra_tidak_bisa_set_price_approved_lewat_endpoint_transition(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-PRICE-MITRA-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'weight' => 3.0,
            'total_price' => 30000,
            'total_amount' => 31000,
            'order_date' => now(),
        ]);

        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'PRICE_APPROVED',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_price_approval',
        ]);
    }

    /**
     * Test: Mitra TIDAK BISA set PAID lewat endpoint transition (hanya alur pembayaran).
     */
    public function test_mitra_tidak_bisa_set_paid_lewat_endpoint_transition(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-PAID-MITRA-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_payment',
            'weight' => 3.0,
            'total_price' => 30000,
            'total_amount' => 31000,
            'order_date' => now(),
        ]);

        $response = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'PAID',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_payment',
        ]);
    }

    /**
     * Test: Pelanggan pemilik order BISA set price_approved lewat endpoint transition.
     */
    public function test_pelanggan_pemilik_bisa_set_price_approved_lewat_endpoint_transition(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-PRICE-CUS-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'weight' => 3.0,
            'total_price' => 30000,
            'total_amount' => 31000,
            'order_date' => now(),
        ]);

        $response = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'price_approved',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('AWAITING_PAYMENT', $response->json('data.status'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_payment',
        ]);
    }

    /**
     * Test: Pelanggan TIDAK BISA set PAID lewat endpoint transition.
     */
    public function test_pelanggan_tidak_bisa_set_paid_lewat_endpoint_transition(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-PAID-CUS-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_payment',
            'weight' => 3.0,
            'total_price' => 30000,
            'total_amount' => 31000,
            'order_date' => now(),
        ]);

        $response = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'PAID',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_payment',
        ]);
    }

    /**
     * Test: Pelanggan pemilik order TIDAK BISA set processing, completed, atau status lain.
     */
    public function test_pelanggan_pemilik_tidak_bisa_set_processing_atau_completed_atau_status_lain(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-CUS-INVALID-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'weight' => 3.0,
            'total_price' => 30000,
            'total_amount' => 31000,
            'order_date' => now(),
        ]);

        // Coba set 'processing'
        $resProcessing = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'processing',
        ]);
        $resProcessing->assertStatus(403);

        // Coba set 'completed'
        $resCompleted = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'completed',
        ]);
        $resCompleted->assertStatus(403);

        // Coba set status lain 'at_store'
        $resAtStore = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'at_store',
        ]);
        $resAtStore->assertStatus(403);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'awaiting_price_approval',
        ]);
    }

    /**
     * Test: Pelanggan lain TIDAK BISA menyentuh order orang lain.
     */
    public function test_pelanggan_lain_tidak_bisa_menyentuh_order_orang(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');

        $orderA = \App\Models\Order::create([
            'order_number' => 'ORD-CUS-ISOLATION-' . uniqid(),
            'customer_id' => $customerA->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'weight' => 2.5,
            'total_price' => 25000,
            'total_amount' => 26000,
            'order_date' => now(),
        ]);

        // Customer B mencoba approve harga milik Customer A
        $resApprove = $this->actingAs($customerB)->postJson("/api/v1/orders/{$orderA->id}/transition", [
            'to_status' => 'price_approved',
        ]);
        $resApprove->assertStatus(403);

        // Customer B mencoba cancel order milik Customer A
        $resCancel = $this->actingAs($customerB)->postJson("/api/v1/orders/{$orderA->id}/transition", [
            'to_status' => 'cancelled',
        ]);
        $resCancel->assertStatus(403);

        // Customer B mencoba status lain pada order milik Customer A
        $resOther = $this->actingAs($customerB)->postJson("/api/v1/orders/{$orderA->id}/transition", [
            'to_status' => 'processing',
        ]);
        $resOther->assertStatus(403);

        $this->assertDatabaseHas('orders', [
            'id' => $orderA->id,
            'status' => 'awaiting_price_approval',
        ]);
    }

    /**
     * Test: Format order tidak melakukan fallback ke estimasi jika order belum ditimbang.
     */
    public function test_format_order_tidak_fallback_ke_estimasi_jika_belum_ditimbang(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $customer = $this->makeUser('customer');

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-UNWEIGHED-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'at_store',
            'weight' => 2.0,
            'actual_weight' => null,
            'estimated_weight' => 2.0,
            'total_price' => 20000,
            'total_amount' => 20000,
            'estimated_price' => 20000,
            'final_price' => null,
            'order_date' => now(),
        ]);

        $response = $this->actingAs($customer)->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data.actual_weight_kg'));
        $this->assertNull($response->json('data.final_amount'));
        $this->assertEquals(2.0, $response->json('data.estimated_weight_kg'));
        $this->assertEquals(20000, $response->json('data.estimated_amount'));
    }

    /**
     * Test: Input timbangan toko 2.3 kg tersimpan, total dihitung ulang, pelanggan tidak bisa setujui dari weighed,
     * dan setelah toko kirim pelanggan bisa setujui hingga awaiting_payment.
     */
    public function test_penimbangan_dan_persetujuan_harga_order_baru(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
            'latitude' => -6.2000,
            'longitude' => 106.8166,
        ]);
        $customer = $this->makeUser('customer');
        $sp = $this->makeServicePrice($store); // price_per_unit = 10000

        $order = \App\Models\Order::create([
            'order_number' => 'ORD-WEIGH-NEW-' . uniqid(),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'at_store',
            'weight' => 2.0,
            'estimated_weight' => 2.0,
            'actual_weight' => null,
            'total_price' => 20000,
            'total_amount' => 20000,
            'estimated_price' => 20000,
            'final_price' => null,
            'order_date' => now(),
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $sp->service_id,
            'quantity' => 2.0,
            'unit' => 'kg',
            'price_per_unit' => 10000,
            'total_price' => 20000,
        ]);

        // 1. Toko melakukan penimbangan: input 2.3 kg dan transisi ke WEIGHED
        $weighResponse = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'WEIGHED',
            'actual_weight_kg' => 2.3,
            'note' => 'total timbangan 2,3kg',
        ]);

        $weighResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(2.3, (float) $order->actual_weight);
        $this->assertEquals(2.3, (float) $order->weight);
        $this->assertEquals(23000, (float) $order->total_amount);
        $this->assertEquals(23000, (float) $order->final_price);
        $this->assertEquals('weighed', $order->status);

        // 2. GET order pelanggan menampilkan berat aktual dan harga final
        $customerGet = $this->actingAs($customer)->getJson("/api/v1/orders/{$order->id}");
        $customerGet->assertStatus(200);
        $this->assertEquals(2.3, $customerGet->json('data.actual_weight_kg'));
        $this->assertEquals(23000, $customerGet->json('data.final_amount'));
        $this->assertEquals('total timbangan 2,3kg', $customerGet->json('data.store_notes'));

        // 3. Pelanggan TIDAK BISA menyetujui langsung dari status weighed
        $prematureApprove = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'PRICE_APPROVED',
        ]);
        // Ditolak karena pelanggan hanya boleh akses update pada awaiting_price_approval/price_approved
        $this->assertTrue(in_array($prematureApprove->status(), [403, 422]));

        $order->refresh();
        $this->assertEquals('weighed', $order->status);

        // 4. Toko mengirimkan hasil timbangan ke pelanggan (weighed -> awaiting_price_approval)
        $storeSendResponse = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'awaiting_price_approval',
            'note' => 'Menunggu persetujuan harga final dari pelanggan.',
        ]);
        $storeSendResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals('awaiting_price_approval', $order->status);

        // 5. Setelah toko kirim, pelanggan BISA menyetujui harga dan otomatis lanjut ke awaiting_payment
        $approveResponse = $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/transition", [
            'to_status' => 'PRICE_APPROVED',
        ]);
        $approveResponse->assertStatus(200);
        $this->assertEquals('AWAITING_PAYMENT', $approveResponse->json('data.status'));

        $order->refresh();
        $this->assertEquals('awaiting_payment', $order->status);
    }
}
