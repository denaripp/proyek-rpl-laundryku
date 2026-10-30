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
     * Kasus 1: Order sudah ditimbang (actual_weight terisi, weighed_at null).
     */
    public function test_order_sudah_ditimbang_actual_weight_terisi(): void
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
     * Kasus 2: Order belum ditimbang (status received_by_store, actual_weight null).
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
     * Kasus 3: Status weighed dengan actual_weight NULL dan weight estimasi 2.0 (seperti Order 28).
     * Jawaban TIDAK boleh "sudah ditimbang" dan TIDAK boleh menyebut weight estimasi "2 kg".
     */
    public function test_status_weighed_actual_weight_null_tidak_boleh_sebut_sudah_ditimbang_dan_tidak_sebut_estimasi(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $store = Store::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORD-TMB-028',
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'weighed',
            'actual_weight' => null,
            'weight' => 2.0, // Ini estimasi pelanggan saat checkout, BUKAN hasil timbang toko
            'weighed_at' => null,
            'order_date' => now(),
        ]);

        $reply = $this->dataQueryService->getOrderProgressResponse($customer, 'apakah pesanan ORD-TMB-028 sudah ditimbang?');

        // Harus gagal pada kode lama karena kode lama menjawab:
        // "Pesanan nomor ORD-TMB-028 sudah ditimbang dengan berat 2 kg. Tahap status saat ini: 'Selesai Ditimbang'."
        $this->assertStringNotContainsString('sudah ditimbang', $reply);
        $this->assertStringNotContainsString('2 kg', $reply);
        $this->assertStringContainsString('belum ditimbang oleh pihak toko', $reply);
    }
}
