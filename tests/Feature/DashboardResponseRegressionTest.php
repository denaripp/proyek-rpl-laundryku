<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardResponseRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_metrics_response_structure_and_values()
    {
        $initialStores = Store::count();
        $initialApproved = Store::where('status', 'approved')->where('is_active', true)->count();
        $initialPending = Store::whereIn('status', ['submitted', 'resubmitted', 'under_review'])->count();
        $initialOrders = Order::count();

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
        $storeOwner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN, 'is_active' => true]);
        $courierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => false]); // suspended user test

        $store = Store::factory()->create([
            'owner_user_id' => $storeOwner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $pendingStore = Store::factory()->create([
            'owner_user_id' => $storeOwner->id,
            'status' => 'submitted',
            'is_active' => true,
        ]);

        $order1 = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'total_amount' => 50000,
        ]);

        $order2 = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'pending_pickup',
            'total_amount' => 25000,
        ]);

        Payment::create([
            'order_id' => $order1->id,
            'payment_method' => 'qris',
            'amount' => 50000,
            'status' => 'paid',
        ]);

        Sanctum::actingAs($admin, ['role:super_admin']);

        $res = $this->getJson('/api/v1/admin/dashboard/metrics');
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('metrics', $json['data']);
        $this->assertArrayHasKey('orders_by_status', $json['data']);
        $this->assertArrayHasKey('users_by_role', $json['data']);
        $this->assertArrayHasKey('recent_orders', $json['data']);

        // Check KPI metrics
        $metrics = $json['data']['metrics'];
        $this->assertEquals($initialOrders + 2, $metrics['total_orders']);
        $this->assertEquals(1, $metrics['completed_orders']);
        $this->assertEquals($initialStores + 2, $metrics['total_stores']);
        $this->assertEquals($initialApproved + 1, $metrics['active_stores']);
        $this->assertEquals($initialPending + 1, $metrics['pending_stores']);
        $this->assertEquals(50000.0, $metrics['total_gmv']);
        $this->assertEquals(5000.0, $metrics['total_platform_revenue']); // 10% commission

        // Check user counts
        $usersByRole = $json['data']['users_by_role'];
        $this->assertGreaterThanOrEqual(1, $usersByRole['customer']);
        $this->assertGreaterThanOrEqual(1, $usersByRole['store_admin']);
        $this->assertGreaterThanOrEqual(1, $usersByRole['courier']);
    }

    public function test_admin_overview_response_structure_and_values()
    {
        $initialStores = Store::count();
        $initialApproved = Store::where('status', 'approved')->count();
        $initialPending = Store::where('status', 'submitted')->count();

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Store::factory()->create(['status' => 'approved']);
        Store::factory()->create(['status' => 'submitted']);

        Sanctum::actingAs($admin, ['role:super_admin']);

        $res = $this->getJson('/api/v1/admin/overview');
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertArrayHasKey('metrics', $json);
        $this->assertEquals($initialStores + 2, $json['metrics']['total_stores']);
        $this->assertEquals($initialPending + 1, $json['metrics']['pending_stores']);
        $this->assertEquals($initialApproved + 1, $json['metrics']['approved_stores']);
    }

    public function test_store_dashboard_response_structure_and_values()
    {
        $owner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN]);
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $customer = User::factory()->create();

        // 1 new order
        Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'pending_pickup',
        ]);

        // 1 processing order
        Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'processing',
        ]);

        // 1 ready for delivery
        Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'ready_for_delivery',
        ]);

        // 1 completed order today
        Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_amount' => 45000,
        ]);

        // 1 courier available
        Courier::factory()->create([
            'store_id' => $store->id,
            'is_available' => true,
            'is_verified' => true,
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $res = $this->getJson("/api/v1/store/dashboard?store_id={$store->id}");
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertTrue($json['success']);
        $data = $json['data'];

        $this->assertEquals(1, $data['metrics']['new_orders']);
        $this->assertEquals(1, $data['metrics']['processing_orders']);
        $this->assertEquals(1, $data['metrics']['ready_for_delivery']);
        $this->assertEquals(1, $data['metrics']['completed_today']);
        $this->assertEquals(45000.0, (float) $data['metrics']['today_revenue']);
    }

    public function test_store_admin_couriers_response_structure_and_values()
    {
        $owner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN]);
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $courierUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        Courier::factory()->create([
            'user_id' => $courierUser->id,
            'store_id' => $store->id,
            'is_available' => true,
            'is_verified' => true,
        ]);

        Sanctum::actingAs($owner, ['role:store_admin']);

        $res = $this->getJson("/api/v1/store-admin/couriers");
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertTrue($json['success']);
        $this->assertCount(1, $json['data']['assigned_couriers']);
    }

    public function test_courier_profile_response_structure_and_values()
    {
        $courierUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        $store = Store::factory()->create(['status' => 'approved', 'is_active' => true]);
        $courier = Courier::factory()->create([
            'user_id' => $courierUser->id,
            'store_id' => $store->id,
            'is_available' => true,
            'is_verified' => true,
        ]);

        CourierTask::create([
            'courier_id' => $courier->id,
            'order_id' => Order::factory()->create(['store_id' => $store->id])->id,
            'task_type' => 'pickup',
            'status' => 'assigned',
        ]);

        CourierTask::create([
            'courier_id' => $courier->id,
            'order_id' => Order::factory()->create(['store_id' => $store->id])->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($courierUser, ['role:courier']);

        $res = $this->getJson("/api/v1/courier/profile");
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['data']['stats']['active_tasks']);
        $this->assertEquals(1, $json['data']['stats']['completed_tasks']);
    }
}
