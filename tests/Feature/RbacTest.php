<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Courier;
use App\Models\StoreStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_store_admin_cannot_access_other_store()
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $admin = User::factory()->create();
        $roleAdmin = Role::where('name', 'store_admin')->first();
        $admin->roles()->attach($roleAdmin, ['store_id' => $storeA->id]);

        // Create a staff record for store A
        StoreStaff::create([
            'store_id' => $storeA->id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Should be allowed to view store A
        $this->assertTrue(Gate::allows('view', $storeA));
        // Should be denied for store B
        $this->assertFalse(Gate::allows('view', $storeB));
    }

    public function test_platform_admin_without_permission_gets_403()
    {
        // Create platform_admin role (but without specific permission)
        $admin = User::factory()->create();
        $role = Role::where('name', 'platform_admin')->first();
        $admin->roles()->attach($role);

        // Ensure this role does NOT have 'delete_store' permission
        $perm = \App\Models\Permission::where('name', 'delete_store')->first();
        if ($perm) {
            $role->permissions()->detach($perm);
        }

        $store = Store::factory()->create();
        $this->actingAs($admin);

        // Should not be allowed to delete store
        $this->assertFalse(Gate::allows('delete', $store));
    }

    public function test_customer_only_owns_orders()
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $store = Store::factory()->create();
        $order1 = Order::factory()->create(['customer_id' => $customer->id, 'store_id' => $store->id]);
        $order2 = Order::factory()->create(['customer_id' => $other->id, 'store_id' => $store->id]);

        $this->actingAs($customer);

        $this->assertTrue(Gate::allows('view', $order1));
        $this->assertFalse(Gate::allows('view', $order2));
    }

    public function test_courier_only_views_assigned_tasks()
    {
        $courierUser = User::factory()->create();
        $courier = Courier::factory()->create(['user_id' => $courierUser->id]);
        $otherCourier = Courier::factory()->create();

        $order1 = Order::factory()->create(['courier_id' => $courierUser->id]);
        $order2 = Order::factory()->create(['courier_id' => $otherCourier->user_id]);

        $this->actingAs($courierUser);

        $this->assertTrue(Gate::allows('view', $order1));
        $this->assertFalse(Gate::allows('view', $order2));
    }
}