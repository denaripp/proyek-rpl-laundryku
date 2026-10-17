<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 18 — Courier dashboard endpoints (toggle, history, earnings).
 *
 * Reuses CourierAssignmentService / DashboardMetricsService for logic.
 * Verifies:
 *  - Toggle availability updates DB + invalidates dashboard cache.
 *  - Tasks endpoint splits active vs history correctly.
 *  - Earnings endpoint returns cached aggregates scoped to the courier.
 *  - Authorization: customer / store_owner / platform_admin cannot read
 *    another courier's earnings or tasks.
 */
class CourierDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $courierUser;
    protected User $customer;
    protected User $otherCourierUser;
    protected Courier $courier;
    protected Courier $otherCourier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Courier user with the courier role
        $this->courierUser = User::factory()->create();
        $this->courierUser->roles()->attach(Role::where('name', 'courier')->first());

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'is_available' => true,
            'is_verified' => true,
            'vehicle_type' => 'motorcycle',
        ]);

        // Another courier (for tenant-isolation checks)
        $this->otherCourierUser = User::factory()->create();
        $this->otherCourierUser->roles()->attach(Role::where('name', 'courier')->first());
        $this->otherCourier = Courier::create([
            'user_id' => $this->otherCourierUser->id,
            'is_available' => true,
            'is_verified' => true,
        ]);

        // Customer (no courier dashboard permission)
        $this->customer = User::factory()->create();
        $this->customer->roles()->attach(Role::where('name', 'customer')->first());
    }

    // ========== TOGGLE AVAILABILITY ==========

    public function test_courier_can_toggle_availability_to_false()
    {
        $this->actingAs($this->courierUser);
        $this->assertTrue($this->courier->is_available);

        $response = $this->patchJson('/api/courier/availability', ['is_available' => false]);

        $response->assertStatus(200);
        $response->assertJson(['id' => $this->courier->id, 'is_available' => false]);

        $this->assertDatabaseHas('couriers', [
            'id' => $this->courier->id,
            'is_available' => 0,
        ]);
    }

    public function test_courier_can_toggle_availability_back_to_true()
    {
        $this->courier->update(['is_available' => false]);

        $this->actingAs($this->courierUser);
        $response = $this->patchJson('/api/courier/availability', ['is_available' => true]);

        $response->assertStatus(200);
        $response->assertJson(['is_available' => true]);
    }

    public function test_availability_toggle_requires_boolean_field()
    {
        $this->actingAs($this->courierUser);
        $response = $this->patchJson('/api/courier/availability', ['is_available' => 'maybe']);
        $response->assertStatus(422);
    }

    public function test_customer_cannot_toggle_availability()
    {
        $this->actingAs($this->customer);
        $response = $this->patchJson('/api/courier/availability', ['is_available' => false]);
        $response->assertStatus(403);
    }

    // ========== TASKS HISTORY ==========

    public function test_tasks_active_returns_only_in_progress_tasks()
    {
        // Active task (for this courier)
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        // Delivered task (terminal, shouldn't show in active)
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->courierUser);
        $response = $this->getJson('/api/courier/tasks?status=active')->assertStatus(200);

        $tasks = $response->json('active_tasks');
        $this->assertCount(1, $tasks);
        $this->assertEquals('accepted', $tasks[0]['status']);
    }

    public function test_tasks_history_returns_only_terminal_tasks()
    {
        // 2 delivered
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now()->subHour(),
        ]);
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'delivery',
            'status' => 'failed',
            'completed_at' => now()->subDay(),
            'failed_at' => now()->subDay(),
        ]);

        // 1 active (should NOT show in history)
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'assigned',
        ]);

        $this->actingAs($this->courierUser);
        $response = $this->getJson('/api/courier/tasks?status=history')->assertStatus(200);

        $history = $response->json('history');
        $this->assertCount(2, $history);

        $statuses = array_column($history, 'status');
        $this->assertContains('delivered', $statuses);
        $this->assertContains('failed', $statuses);
        $this->assertNotContains('assigned', $statuses);

        // Pagination present
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    public function test_tasks_history_does_not_leak_other_courier_data()
    {
        // Courier B's history
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->otherCourier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        // Courier A has no history
        $this->actingAs($this->courierUser);
        $response = $this->getJson('/api/courier/tasks?status=history')->assertStatus(200);

        $this->assertEquals(0, $response->json('pagination.total'));
        $this->assertCount(0, $response->json('history'));
    }

    public function test_customer_cannot_access_courier_tasks()
    {
        $this->actingAs($this->customer);
        $this->getJson('/api/courier/tasks')->assertStatus(403);
        $this->getJson('/api/courier/tasks?status=active')->assertStatus(403);
        $this->getJson('/api/courier/tasks?status=history')->assertStatus(403);
    }

    // ========== EARNINGS ==========

    public function test_earnings_endpoint_returns_scoped_aggregates()
    {
        // Add a delivered task to make earnings non-zero
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->courierUser);
        $response = $this->getJson('/api/courier/earnings')->assertStatus(200);

        $response->assertJsonStructure([
            'courier_id',
            'is_available',
            'earnings' => ['today', 'month', 'lifetime', 'currency'],
            'tasks' => ['active', 'today_completed', 'lifetime_completed', 'avg_rating'],
            'recent_delivered',
            'window' => ['today', 'month_start'],
        ]);

        // Default courier fee = 5000 per delivered task (sesuai DashboardMetricsService::courierEarnings)
        $this->assertEquals(5000.0, $response->json('earnings.today'));
        $this->assertEquals(5000.0, $response->json('earnings.lifetime'));
    }

    public function test_earnings_isolated_per_courier()
    {
        // Courier A: 1 delivered
        CourierTask::create([
            'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        // Courier B: 2 delivered
        for ($i = 0; $i < 2; $i++) {
            CourierTask::create([
                'order_id' => Order::factory()->create(['customer_id' => $this->customer->id])->id,
                'courier_id' => $this->otherCourier->id,
                'task_type' => 'pickup',
                'status' => 'delivered',
                'completed_at' => now(),
            ]);
        }

        // Courier A reads his own
        $this->actingAs($this->courierUser);
        $rA = $this->getJson('/api/courier/earnings')->assertStatus(200);
        $this->assertEquals(5000.0, $rA->json('earnings.lifetime'));

        // Courier B reads his own — different scope, different value
        $this->actingAs($this->otherCourierUser);
        $rB = $this->getJson('/api/courier/earnings')->assertStatus(200);
        $this->assertEquals(10000.0, $rB->json('earnings.lifetime'));
    }

    public function test_customer_cannot_access_courier_earnings()
    {
        $this->actingAs($this->customer);
        $this->getJson('/api/courier/earnings')->assertStatus(403);
    }

    public function test_courier_without_profile_returns_404_on_earnings()
    {
        $userNoCourier = User::factory()->create();
        $userNoCourier->roles()->attach(Role::where('name', 'courier')->first());

        $this->actingAs($userNoCourier);
        $this->getJson('/api/courier/earnings')->assertStatus(404);
    }

    // ========== TOGGLE INVALIDATES CACHE ==========

    public function test_toggle_availability_invalidates_dashboard_cache()
    {
        // Prime metrics cache by hitting dashboard
        $this->actingAs($this->courierUser);
        $this->getJson('/api/courier/dashboard')->assertStatus(200);

        // Toggle
        $this->patchJson('/api/courier/availability', ['is_available' => false])
            ->assertStatus(200);

        // Verify dashboard reflects the change immediately (no stale cache)
        $r = $this->getJson('/api/courier/dashboard');
        $this->assertFalse($r->json('stats.is_available'));
    }
}