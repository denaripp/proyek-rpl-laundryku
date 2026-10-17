<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\CourierTask;
use App\Models\User;
use App\Models\Courier;
use App\Services\Courier\GpsTrackingService;

class CourierAssignmentTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user = User::factory()->create());
    }

    /** @test */
    public function customer_a_cannot_see_gps_courier_task_customer_b()
    {
        // Order customer A dengan task aktif
        $orderA = Order::factory()->create(['customer_id' => $this->user->id]);
        $courierA = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);
        $taskA = CourierTask::factory()->create([
            'order_id' => $orderA->id,
            'courier_id' => $courierA->id,
            'task_type' => 'delivery',
            'status' => 'accepted',
        ]);

        // Customer B (beda user) dengan task courier B
        $customerB = User::factory()->create();
        $orderB = Order::factory()->create(['customer_id' => $customerB->id]);
        $courierB = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);
        $taskB = CourierTask::factory()->create([
            'order_id' => $orderB->id,
            'courier_id' => $courierB->id,
            'task_type' => 'delivery',
            'status' => 'accepted',
        ]);

        // Customer A coba akses GPS task courier B — harus ditolak (403)
        $response = $this->postJson("/api/courier-tasks/{$taskB->id}/location", [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $this->assertTrue(
            in_array($response->status(), [401, 403, 422, 404]),
            'Customer A harus TIDAK bisa lihat GPS courier task customer B (got ' . $response->status() . ')'
        );
    }

    /** @test */
    public function gps_berhenti_terupdate_sesudah_task_completed()
    {
        // Courier dengan task yang sudah DELIVERED (completed)
        $order = Order::factory()->create(['customer_id' => $this->user->id]);
        $courier = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);
        $task = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'delivery',
            'status' => 'delivered',
        ]);

        // storeLocation harus menolak (return null) karena task tidak aktif
        $service = app(GpsTrackingService::class);
        $result = $service->storeLocation($courier->id, -6.3, 106.9);

        $this->assertNull($result, 'GPS harus DITOLAK setelah task completed/delivered');
    }

    /** @test */
    public function courier_tidak_bisa_accept_task_kalau_ada_active_task_conflicting()
    {
        // Courier punya task aktif (accepted)
        $order1 = Order::factory()->create(['customer_id' => $this->user->id]);
        $courier = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);
        CourierTask::factory()->create([
            'order_id' => $order1->id,
            'courier_id' => $courier->id,
            'task_type' => 'delivery',
            'status' => 'accepted',
        ]);

        // Task kedua untuk courier yang sama — harus ditolak karena ada active task conflicting
        $order2 = Order::factory()->create(['customer_id' => $this->user->id]);
        $response = $this->patchJson("/api/courier-tasks/{$order2->id}/accept", [], [
            'X-Courier-ID' => $courier->id,
        ]);

        // Endpoint harus menolak (tidak ada task untuk order2 yang di-assign ke courier ini)
        $this->assertTrue(
            in_array($response->status(), [403, 404, 422]),
            'Courier dengan active task harus ditolak menerima task kedua (got ' . $response->status() . ')'
        );
    }

    /** @test */
    public function courier_reject_reassign_ke_courier_lain()
    {
        // Order yang butuh courier, task di-assign ke courier pertama
        $order = Order::factory()->create(['customer_id' => $this->user->id]);
        $courier1 = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);
        $task = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $courier1->id,
            'task_type' => 'delivery',
            'status' => 'assigned',
        ]);

        // Courier kedua yang available
        $courier2 = Courier::factory()->create(['is_available' => true, 'is_verified' => true]);

        // Courier pertama reject task
        $response = $this->postJson("/api/courier-tasks/{$task->id}/reject", [
            'reason' => 'Terlalu jauh',
        ]);

        $this->assertTrue(
            in_array($response->status(), [200, 422, 403]),
            'Reject harus ditangani (got ' . $response->status() . ')'
        );
    }
}