<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiDataQueryService;
use App\Services\Ai\AiOrderStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDataQueryServiceWeighingTest extends TestCase
{
    use RefreshDatabase;

    protected AiDataQueryService $dataQueryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataQueryService = new AiDataQueryService(new AiOrderStatsService());
    }

    /**
     * Kasus 1: Order sudah ditimbang (status pasca-timbang, actual_weight terisi, weighed_at null).
     */
    public function test_order_sudah_ditimbang_actual_weight_terisi_weighed_at_null(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $store = Store::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORD-TMB-001',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'actual_weight' => 2.5,
            'weight' => 2.0,
            'weighed_at' => null,
            'order_date' => now(),
        ]);

        $reply = $this->dataQueryService->getOrderProgressResponse($customer, 'apakah pesanan ORD-TMB-001 sudah ditimbang?');

        $this->assertStringContainsString('sudah ditimbang dengan berat 2.5 kg', $reply);
        $this->assertStringNotContainsString('belum ditimbang oleh pihak toko', $reply);
    }

    /**
     * Kasus 2: Order belum ditimbang (status received_by_store, actual_weight null, weighed_at null).
     */
    public function test_order_belum_ditimbang(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $store = Store::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORD-TMB-002',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'received_by_store',
            'actual_weight' => null,
            'weight' => 2.0,
            'weighed_at' => null,
            'order_date' => now(),
        ]);

        $reply = $this->dataQueryService->getOrderProgressResponse($customer, 'apakah pesanan ORD-TMB-002 sudah ditimbang?');

        $this->assertStringContainsString('belum ditimbang oleh pihak toko', $reply);
    }

    /**
     * Kasus 3: Order status pasca-timbang tetapi actual_weight null dan weight bernilai 0.
     * Tidak boleh menampilkan "0 kg", harus menyatakan jujur bahwa berat belum tersedia.
     */
    public function test_order_status_pasca_timbang_tetapi_actual_weight_null(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $store = Store::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORD-TMB-003',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'awaiting_price_approval',
            'actual_weight' => null,
            'weight' => 0.0,
            'weighed_at' => null,
            'order_date' => now(),
        ]);

        $reply = $this->dataQueryService->getOrderProgressResponse($customer, 'apakah pesanan ORD-TMB-003 sudah ditimbang?');

        $this->assertStringNotContainsString('belum ditimbang oleh pihak toko', $reply);
        $this->assertStringContainsString('sudah ditimbang (berat belum tersedia)', $reply);
        $this->assertStringNotContainsString('0 kg', $reply);
    }

    /**
     * Kasus 4: Status weighed dengan actual_weight null dan weight 0.
     */
    public function test_order_weighed_dengan_berat_nol_menampilkan_pesan_jujur_tanpa_nol_kg(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $store = Store::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORD-TMB-004',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'weighed',
            'actual_weight' => null,
            'weight' => 0.0,
            'weighed_at' => null,
            'order_date' => now(),
        ]);

        $reply = $this->dataQueryService->getOrderProgressResponse($customer, 'apakah pesanan ORD-TMB-004 sudah ditimbang?');

        $this->assertStringNotContainsString('belum ditimbang oleh pihak toko', $reply);
        $this->assertStringContainsString('sudah ditimbang (berat belum tersedia)', $reply);
        $this->assertStringNotContainsString('0 kg', $reply);
    }
}
