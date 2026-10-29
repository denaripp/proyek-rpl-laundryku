<?php

namespace Tests\Feature;

use App\Enums\CourierTaskStatus;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierPortalTransitionTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwner;
    protected Store $store;
    protected User $courierUser;
    protected Courier $courier;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

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
            'name' => 'Laundry Express',
            'is_active' => true,
            'status' => 'approved',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        $courierRole = Role::where('name', 'courier')->first();
        $this->courierUser = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'name' => 'Kurir Express',
            'email' => 'kurir.express@laundryku.test',
        ]);
        if ($courierRole) {
            $this->courierUser->roles()->attach($courierRole->id);
        }

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 EXP',
            'is_verified' => true,
            'is_available' => true,
        ]);

        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Pelanggan Setia',
        ]);
    }

    public function test_transition_task_returns_404_if_order_not_found(): void
    {
        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->postJson('/api/v1/courier/tasks/999999/transition', [
            'to_status' => 'picked_up',
        ]);

        $res->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ]);
    }

    public function test_transition_task_returns_403_if_courier_not_authorized_for_order(): void
    {
        $otherCourierUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        $otherCourier = Courier::create([
            'user_id' => $otherCourierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_available' => true,
        ]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-UNAUTH-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::COURIER_ASSIGNED->value,
            'courier_id' => $otherCourierUser->id,
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'picked_up',
        ]);

        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengubah tugas pesanan ini.',
            ]);
    }

    public function test_transition_task_returns_422_on_invalid_status_transition(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-INVALID-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::COURIER_ASSIGNED->value,
            'courier_id' => $this->courierUser->id,
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        // Cannot jump directly from courier_assigned to completed
        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'completed',
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_transition_task_pickup_flow_updates_order_and_task_and_returns_canonical_json(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-PICKUP-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::COURIER_ASSIGNED->value,
            'courier_id' => $this->courierUser->id,
        ]);

        $task = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => CourierTaskStatus::ACCEPTED->value,
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'picked_up',
        ]);

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_id',
                    'order_number',
                    'task_type',
                    'status',
                    'payment_status',
                    'is_assigned_to_me',
                    'courier_id',
                ],
            ]);

        $this->assertEquals(OrderStatus::PICKED_UP->value, $order->fresh()->status);
        $this->assertEquals(CourierTaskStatus::PICKED_UP->value, $task->fresh()->status);
    }

    public function test_transition_task_delivery_flow_completes_task_and_order(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-DELIV-01',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::OUT_FOR_DELIVERY->value,
            'courier_id' => $this->courierUser->id,
        ]);

        $task = CourierTask::factory()->create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'delivery',
            'status' => CourierTaskStatus::ACCEPTED->value,
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'delivered',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status tugas berhasil diperbarui.',
            ]);

        $freshOrder = $order->fresh();
        $this->assertEquals(OrderStatus::COMPLETED->value, $freshOrder->status);
        $this->assertNotNull($freshOrder->completed_at);

        $freshTask = $task->fresh();
        $this->assertEquals(CourierTaskStatus::DELIVERED->value, $freshTask->status);
        $this->assertNotNull($freshTask->completed_at);
    }
}
