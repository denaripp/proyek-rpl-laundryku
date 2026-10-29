<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourierAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_assign_delivery_courier_via_admin_portal()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $store = Store::factory()->create([
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);

        $customer = User::factory()->create();

        $courierUser = User::factory()->create();
        $courier = Courier::factory()->create([
            'user_id' => $courierUser->id,
            'store_id' => $store->id,
            'is_verified' => true,
            'is_available' => true,
            'current_latitude' => -6.9180,
            'current_longitude' => 107.6200,
        ]);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'ready_for_delivery',
            'delivery_latitude' => -6.9200,
            'delivery_longitude' => 107.6250,
        ]);

        Sanctum::actingAs($admin, ['role:super_admin']);

        $response = $this->postJson("/api/v1/admin/orders/{$order->id}/assign-courier", [
            'task_type' => 'delivery',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('courier_tasks', [
            'order_id' => $order->id,
            'task_type' => 'delivery',
            'status' => 'assigned',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'ADMIN_ASSIGN_COURIER',
            'resource_id' => (string) $order->id,
        ]);
    }

    public function test_super_admin_can_assign_courier_via_v1_order_endpoint()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $store = Store::factory()->create([
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);

        $customer = User::factory()->create();

        $courierUser = User::factory()->create();
        $courier = Courier::factory()->create([
            'user_id' => $courierUser->id,
            'store_id' => $store->id,
            'is_verified' => true,
            'is_available' => true,
            'current_latitude' => -6.9180,
            'current_longitude' => 107.6200,
        ]);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'ready_for_delivery',
            'delivery_latitude' => -6.9200,
            'delivery_longitude' => 107.6250,
        ]);

        Sanctum::actingAs($admin, ['role:super_admin']);

        $response = $this->postJson("/api/v1/orders/{$order->id}/assign-courier", [
            'task_type' => 'delivery',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('courier_tasks', [
            'order_id' => $order->id,
            'task_type' => 'delivery',
            'status' => 'assigned',
        ]);
    }
}
