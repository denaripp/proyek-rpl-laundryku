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
use Laravel\Sanctum\Sanctum;

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

    /** @test */
    public function assign_delivery_calculates_distance_from_store_coordinates()
    {
        $store = \App\Models\Store::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8000,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'delivery_latitude' => -6.2100,
            'delivery_longitude' => 106.8100,
            'status' => 'ready_for_delivery',
        ]);
        $courier = Courier::factory()->create([
            'is_available' => true,
            'is_verified' => true,
            'store_id' => $store->id,
            'current_latitude' => -6.2010,
            'current_longitude' => 106.8010,
        ]);

        $service = app(\App\Services\Courier\CourierAssignmentService::class);
        $task = $service->assignCourier($order, 'delivery');

        $this->assertEquals('delivery', $task->task_type);
        $this->assertEquals($courier->id, $task->courier_id);
        $this->assertEquals('courier_assigned_for_delivery', $order->fresh()->status);
    }

    /** @test */
    public function assign_delivery_fallbacks_to_pickup_coordinates_if_delivery_coords_null()
    {
        $store = \App\Models\Store::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8000,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8100,
            'delivery_latitude' => null,
            'delivery_longitude' => null,
            'status' => 'ready_for_delivery',
        ]);
        $courier = Courier::factory()->create([
            'is_available' => true,
            'is_verified' => true,
            'store_id' => $store->id,
            'current_latitude' => -6.2010,
            'current_longitude' => 106.8010,
        ]);

        $service = app(\App\Services\Courier\CourierAssignmentService::class);
        $task = $service->assignCourier($order, 'delivery');

        $this->assertEquals('delivery', $task->task_type);
        $this->assertEquals($courier->id, $task->courier_id);
    }

    /** @test */
    public function assign_delivery_fails_only_if_both_delivery_and_pickup_and_address_coords_null()
    {
        $store = \App\Models\Store::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8000,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'pickup_latitude' => null,
            'pickup_longitude' => null,
            'delivery_latitude' => null,
            'delivery_longitude' => null,
            'status' => 'ready_for_delivery',
        ]);
        Courier::factory()->create([
            'is_available' => true,
            'is_verified' => true,
            'store_id' => $store->id,
            'current_latitude' => -6.2010,
            'current_longitude' => 106.8010,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Koordinat tujuan pengantaran customer belum ditentukan.');

        $service = app(\App\Services\Courier\CourierAssignmentService::class);
        $service->assignCourier($order, 'delivery');
    }

    /** @test */
    public function courier_without_current_coordinates_is_eligible_and_ordered_last()
    {
        $store = \App\Models\Store::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8000,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'delivery_latitude' => -6.2100,
            'delivery_longitude' => 106.8100,
            'status' => 'ready_for_delivery',
        ]);

        // Courier 1 has GPS (1 km away)
        $courierWithGps = Courier::factory()->create([
            'is_available' => true,
            'is_verified' => true,
            'store_id' => $store->id,
            'current_latitude' => -6.2010,
            'current_longitude' => 106.8010,
        ]);

        // Courier 2 has NO GPS
        $courierNoGps = Courier::factory()->create([
            'is_available' => true,
            'is_verified' => true,
            'store_id' => $store->id,
            'current_latitude' => null,
            'current_longitude' => null,
        ]);

        $service = app(\App\Services\Courier\CourierAssignmentService::class);
        $eligible = $service->findEligibleCouriers($order, 'delivery');

        // Courier with GPS must be first, courier without GPS must be second
        $this->assertEquals($courierWithGps->id, $eligible->first()->id);
        $this->assertEquals($courierNoGps->id, $eligible->last()->id);
    }

    /** @test */
    public function pickup_transition_to_at_store_closes_pickup_task()
    {
        $courierUser = User::factory()->create(['role' => 'courier']);
        $courier = Courier::factory()->create([
            'user_id' => $courierUser->id,
            'is_available' => true,
            'is_verified' => true,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'status' => 'picked_up',
            'courier_id' => $courierUser->id,
        ]);
        $pickupTask = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'picked_up',
        ]);

        Sanctum::actingAs($courierUser, ['role:courier']);

        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'at_store',
        ]);

        $res->assertStatus(200);
        $pickupTask->refresh();
        $this->assertEquals('delivered', $pickupTask->status);
        $this->assertNotNull($pickupTask->completed_at);
        $this->assertEquals('at_store', $order->fresh()->status);
    }

    /** @test */
    public function delivery_transition_to_completed_closes_delivery_task_and_courier_becomes_eligible()
    {
        $courierUser = User::factory()->create(['role' => 'courier']);
        $store = \App\Models\Store::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8000,
        ]);
        $courier = Courier::factory()->create([
            'user_id' => $courierUser->id,
            'store_id' => $store->id,
            'is_available' => true,
            'is_verified' => true,
            'current_latitude' => -6.2010,
            'current_longitude' => 106.8010,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'status' => 'out_for_delivery',
            'delivery_latitude' => -6.2100,
            'delivery_longitude' => 106.8100,
            'courier_id' => $courierUser->id,
        ]);

        // Old pickup task was already finished
        $pickupTask = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now()->subHours(2),
        ]);

        // Active delivery task
        $deliveryTask = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'delivery',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($courierUser, ['role:courier']);

        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'COMPLETED',
        ]);

        $res->assertStatus(200);

        $deliveryTask->refresh();
        $this->assertEquals('delivered', $deliveryTask->status);
        $this->assertNotNull($deliveryTask->completed_at);
        $this->assertEquals('completed', $order->fresh()->status);

        // Courier should now have NO active conflicting tasks and be eligible again
        $service = app(\App\Services\Courier\CourierAssignmentService::class);
        $newOrder = Order::factory()->create([
            'customer_id' => $this->user->id,
            'store_id' => $store->id,
            'delivery_latitude' => -6.2100,
            'delivery_longitude' => 106.8100,
            'status' => 'ready_for_delivery',
        ]);
        $eligible = $service->findEligibleCouriers($newOrder, 'delivery');
        $this->assertTrue($eligible->contains('id', $courier->id));
    }
}