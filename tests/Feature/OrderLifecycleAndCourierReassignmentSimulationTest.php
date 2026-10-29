<?php

namespace Tests\Feature;

use App\Enums\CourierTaskStatus;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierGps;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Courier\CourierAssignmentService;
use App\Services\Order\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleAndCourierReassignmentSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwner;
    protected Store $store;
    protected User $courierUser;
    protected Courier $courier;
    protected User $customer;
    protected CourierAssignmentService $assignmentService;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assignmentService = app(CourierAssignmentService::class);
        $this->orderService = app(OrderService::class);

        $storeAdminRole = Role::where('name', 'store_admin')->first();
        $this->storeOwner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Pemilik Toko',
        ]);
        if ($storeAdminRole) {
            $this->storeOwner->roles()->attach($storeAdminRole->id);
        }

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundryku Clean & Fast',
            'is_active' => true,
            'status' => 'approved',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        $courierRole = Role::where('name', 'courier')->first();
        $this->courierUser = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'name' => 'Budi Si Kurir Handal',
            'email' => 'budi.kurir@laundryku.test',
        ]);
        if ($courierRole) {
            $this->courierUser->roles()->attach($courierRole->id);
        }

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 9999 KRR',
            'is_verified' => true,
            'is_available' => true,
            'current_latitude' => -6.2090,
            'current_longitude' => 106.8450,
        ]);

        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Siti Customer',
        ]);
    }

    /**
     * Test full lifecycle simulation:
     * Order 1: Pickup -> Store -> Processed -> Delivery -> Completed.
     * Followed immediately by successfully assigning the SAME courier to Order 2.
     */
    public function test_complete_order_lifecycle_and_reassignment_to_second_order(): void
    {
        // -------------------------------------------------------------
        // 1. ORDER 1: Creation & Pickup Assignment
        // -------------------------------------------------------------
        $order1 = Order::factory()->create([
            'order_number' => 'SIM-ORD-001',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::CREATED->value,
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8400,
            'delivery_latitude' => -6.2100,
            'delivery_longitude' => 106.8400,
        ]);

        // Assign courier for pickup
        $pickupTask = $this->assignmentService->assignCourier($order1, 'pickup');
        $this->assertEquals($this->courier->id, $pickupTask->courier_id);
        $this->assertEquals(CourierTaskStatus::ASSIGNED->value, $pickupTask->status);
        $this->assertEquals(OrderStatus::COURIER_ASSIGNED->value, $order1->fresh()->status);

        // -------------------------------------------------------------
        // 2. ORDER 1: Courier Pickup Transitions (via Courier Portal API)
        // -------------------------------------------------------------
        Sanctum::actingAs($this->courierUser, ['role:courier']);

        // 2a. Courier to customer
        $res = $this->postJson("/api/v1/courier/tasks/{$order1->id}/transition", [
            'to_status' => 'courier_to_customer',
        ]);
        $res->assertStatus(200);
        $this->assertEquals(OrderStatus::COURIER_TO_CUSTOMER->value, $order1->fresh()->status);
        $this->assertEquals(CourierTaskStatus::ACCEPTED->value, $pickupTask->fresh()->status);

        // 2b. Picked up
        $res = $this->postJson("/api/v1/courier/tasks/{$order1->id}/transition", [
            'to_status' => 'picked_up',
        ]);
        $res->assertStatus(200);
        $this->assertEquals(OrderStatus::PICKED_UP->value, $order1->fresh()->status);
        $this->assertEquals(CourierTaskStatus::PICKED_UP->value, $pickupTask->fresh()->status);

        // 2c. At store (Pickup task must be CLOSED)
        $res = $this->postJson("/api/v1/courier/tasks/{$order1->id}/transition", [
            'to_status' => 'at_store',
        ]);
        $res->assertStatus(200);
        $this->assertEquals('at_store', $order1->fresh()->status);

        $freshPickupTask = $pickupTask->fresh();
        $this->assertEquals(CourierTaskStatus::DELIVERED->value, $freshPickupTask->status);
        $this->assertNotNull($freshPickupTask->completed_at);

        // -------------------------------------------------------------
        // 3. ORDER 1: Store Processing & Preparation for Delivery
        // -------------------------------------------------------------
        $order1->refresh();
        $this->orderService->transition($order1, OrderStatus::RECEIVED_BY_STORE->value, $this->storeOwner);
        $this->orderService->transition($order1, OrderStatus::WEIGHED->value, $this->storeOwner);
        $this->orderService->transition($order1, 'awaiting_price_approval', $this->storeOwner);
        $this->orderService->transition($order1, 'price_approved', $this->customer);
        $this->orderService->transition($order1, 'awaiting_payment', $this->storeOwner);
        $this->orderService->transition($order1, OrderStatus::PAID->value, $this->storeOwner);
        $this->orderService->transition($order1, OrderStatus::PROCESSING->value, $this->storeOwner);
        $this->orderService->transition($order1, OrderStatus::READY_FOR_DELIVERY->value, $this->storeOwner);

        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $order1->fresh()->status);

        // -------------------------------------------------------------
        // 4. ORDER 1: Delivery Assignment & Delivery Transitions
        // -------------------------------------------------------------
        $order1->refresh();
        // Assign the courier for delivery
        $deliveryTask = $this->assignmentService->assignCourier($order1, 'delivery');
        $this->assertEquals($this->courier->id, $deliveryTask->courier_id);
        $this->assertEquals(CourierTaskStatus::ASSIGNED->value, $deliveryTask->status);
        $this->assertEquals(OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value, $order1->fresh()->status);

        // Courier starts delivery
        $res = $this->postJson("/api/v1/courier/tasks/{$order1->id}/transition", [
            'to_status' => 'out_for_delivery',
        ]);
        $res->assertStatus(200);
        $this->assertEquals(OrderStatus::OUT_FOR_DELIVERY->value, $order1->fresh()->status);
        $this->assertEquals(CourierTaskStatus::ACCEPTED->value, $deliveryTask->fresh()->status);

        // Courier delivers to customer -> order becomes completed, delivery task CLOSED
        $res = $this->postJson("/api/v1/courier/tasks/{$order1->id}/transition", [
            'to_status' => 'delivered',
        ]);
        $res->assertStatus(200);

        $freshOrder1 = $order1->fresh();
        $this->assertEquals(OrderStatus::COMPLETED->value, $freshOrder1->status);
        $this->assertNotNull($freshOrder1->completed_at);

        $freshDeliveryTask = $deliveryTask->fresh();
        $this->assertEquals(CourierTaskStatus::DELIVERED->value, $freshDeliveryTask->status);
        $this->assertNotNull($freshDeliveryTask->completed_at);

        // -------------------------------------------------------------
        // 5. VERIFY COURIER FREEDOM & REASSIGNMENT TO ORDER 2
        // -------------------------------------------------------------
        // Courier must have 0 active tasks now
        $activeTasks = CourierTask::where('courier_id', $this->courier->id)
            ->whereIn('status', CourierTask::ACTIVE_STATUSES)
            ->count();
        $this->assertEquals(0, $activeTasks, 'Courier should have 0 active tasks after completing order 1');

        // Courier must appear in eligible list for a new order
        $order2 = Order::factory()->create([
            'order_number' => 'SIM-ORD-002',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::CREATED->value,
            'pickup_latitude' => -6.2110,
            'pickup_longitude' => 106.8420,
            'delivery_latitude' => -6.2110,
            'delivery_longitude' => 106.8420,
        ]);

        $eligibleCouriers = $this->assignmentService->findEligibleCouriers($order2, 'pickup');
        $this->assertTrue(
            $eligibleCouriers->contains('id', $this->courier->id),
            'The same courier must be eligible for Order 2'
        );

        // Successfully assign the SAME courier to Order 2 without any conflict exception!
        $order2PickupTask = $this->assignmentService->assignCourier($order2, 'pickup');
        $this->assertNotNull($order2PickupTask);
        $this->assertEquals($this->courier->id, $order2PickupTask->courier_id);
        $this->assertEquals(CourierTaskStatus::ASSIGNED->value, $order2PickupTask->status);
        $this->assertEquals(OrderStatus::COURIER_ASSIGNED->value, $order2->fresh()->status);
    }
}
