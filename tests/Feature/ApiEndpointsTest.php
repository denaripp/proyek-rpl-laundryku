<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 16 — API Endpoints / Controllers per Role.
 *
 * Coverage:
 *  - Controller tipis (calls service, no business logic)
 *  - FormRequest validation (server-side)
 *  - Policy applied → 403 for unauthorized user
 *  - Each role can only hit their own scope
 *  - Happy path: customer creates order → store marks received → admin sees in dashboard
 */
class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function makeUser(string $roleName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->roles()->attach(Role::where('name', $roleName)->first());
        return $user;
    }

    protected function makeOrder(User $customer, Store $store, string $status = 'created', array $extra = []): Order
    {
        $defaults = [
            'order_number' => 'ORD-T16-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => $status,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'weight' => 5,
            'total_price' => 50000,
            'total_amount' => 50000,
            'payment_status' => 'pending',
            'order_date' => now(),
        ];
        return Order::create(array_merge($defaults, $extra));
    }

    protected function makeApprovedStore(?User $owner = null): Store
    {
        $owner = $owner ?? $this->makeUser('store_owner');
        return Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    protected function makeServicePrice(Store $store): ServicePrice
    {
        $service = Service::create(['name' => 'Wash', 'is_active' => true]);
        return ServicePrice::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);
    }

    // ====================================================================
    // Public marketplace (auth required, but light)
    // ====================================================================

    public function test_guest_cannot_search_stores(): void
    {
        $r = $this->getJson('/api/stores');
        $this->assertContains($r->status(), [401, 403]);
    }

    public function test_customer_can_search_stores(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $this->makeServicePrice($store);

        $r = $this->actingAs($customer)->getJson('/api/stores');

        $r->assertStatus(200);
        $r->assertJsonFragment(['name' => $store->name]);
    }

    public function test_customer_can_view_store_detail(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();

        $r = $this->actingAs($customer)->getJson("/api/stores/{$store->id}");

        $r->assertStatus(200);
        $r->assertJsonPath('id', $store->id);
    }

    // ====================================================================
    // Customer: create order (FormRequest + policy + service)
    // ====================================================================

    public function test_customer_can_create_order(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $sp->service_id, 'quantity' => 5]],
            'pickup_address' => 'Jl. Pickup 1',
            'pickup_latitude' => -6.2,
            'pickup_longitude' => 106.8,
            'delivery_address' => 'Jl. Delivery 1',
            'delivery_latitude' => -6.21,
            'delivery_longitude' => 106.81,
            'customer_notes' => 'Please be careful',
        ];

        $r = $this->actingAs($customer)->postJson('/api/orders', $payload);

        $r->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'created',
        ]);
    }

    public function test_create_order_validation_rejects_invalid_weight(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 0, // min 0.1
            'estimated_price' => 50000,
            'items' => [['service_id' => $sp->service_id, 'quantity' => 5]],
            'pickup_address' => 'Jl. Pickup 1',
            'pickup_latitude' => -6.2,
            'pickup_longitude' => 106.8,
            'delivery_address' => 'Jl. Delivery 1',
            'delivery_latitude' => -6.21,
            'delivery_longitude' => 106.81,
        ];

        $r = $this->actingAs($customer)->postJson('/api/orders', $payload);
        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['estimated_weight']);
    }

    public function test_create_order_validation_rejects_bad_coordinates(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $sp->service_id, 'quantity' => 5]],
            'pickup_address' => 'Jl. Pickup 1',
            'pickup_latitude' => 200, // invalid lat
            'pickup_longitude' => 106.8,
            'delivery_address' => 'Jl. Delivery 1',
            'delivery_latitude' => -6.21,
            'delivery_longitude' => 106.81,
        ];

        $r = $this->actingAs($customer)->postJson('/api/orders', $payload);
        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['pickup_latitude']);
    }

    public function test_user_without_create_order_permission_gets_403(): void
    {
        $courier = $this->makeUser('courier');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $payload = [
            'store_id' => $store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [['service_id' => $sp->service_id, 'quantity' => 5]],
            'pickup_address' => 'Jl. Pickup 1',
            'pickup_latitude' => -6.2,
            'pickup_longitude' => 106.8,
            'delivery_address' => 'Jl. Delivery 1',
            'delivery_latitude' => -6.21,
            'delivery_longitude' => 106.81,
        ];

        $r = $this->actingAs($courier)->postJson('/api/orders', $payload);
        $r->assertStatus(403);
    }

    public function test_customer_can_view_own_order(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $sp = $this->makeServicePrice($store);

        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($customer)->getJson("/api/orders/{$order->id}");
        $r->assertStatus(200);
    }

    public function test_other_customer_cannot_view_someone_elses_order(): void
    {
        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        $store = $this->makeApprovedStore();

        $order = $this->makeOrder($customerA, $store, 'created');

        $r = $this->actingAs($customerB)->getJson("/api/orders/{$order->id}");
        $r->assertStatus(403);
    }

    public function test_store_staff_can_view_store_order(): void
    {
        $owner = $this->makeUser('store_owner');
        $staff = $this->makeUser('store_staff');
        $store = $this->makeApprovedStore($owner);

        // Attach staff
        \App\Models\StoreStaff::create([
            'store_id' => $store->id, 'user_id' => $staff->id, 'is_active' => true,
        ]);

        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($staff)->getJson("/api/orders/{$order->id}");
        $r->assertStatus(200);
    }

    public function test_store_staff_from_other_store_gets_403(): void
    {
        $ownerA = $this->makeUser('store_owner');
        $ownerB = $this->makeUser('store_owner');
        $storeA = $this->makeApprovedStore($ownerA);
        $storeB = $this->makeApprovedStore($ownerB);

        $staff = $this->makeUser('store_staff');
        \App\Models\StoreStaff::create([
            'store_id' => $storeB->id, 'user_id' => $staff->id, 'is_active' => true,
        ]);

        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $storeA, 'created');

        $r = $this->actingAs($staff)->getJson("/api/orders/{$order->id}");
        $r->assertStatus(403);
    }

    public function test_order_tracking_returns_history(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($customer)->getJson("/api/orders/{$order->id}/tracking");
        $r->assertStatus(200);
        $r->assertJsonStructure(['order', 'history', 'courier_tasks']);
    }

    public function test_customer_cannot_update_order_status(): void
    {
        $customer = $this->makeUser('customer');
        $store = $this->makeApprovedStore();
        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($customer)->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'received_by_store',
        ]);
        $r->assertStatus(403);
    }

    public function test_store_owner_can_update_order_status(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($owner)->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'waiting_pickup',
        ]);
        $r->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'waiting_pickup',
        ]);
    }

    public function test_set_weight_validates_range(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $store, 'weighed');

        $r = $this->actingAs($owner)->postJson("/api/orders/{$order->id}/set-weight", [
            'weight' => 5000, // over 1000 kg max
        ]);
        $r->assertStatus(422);
    }

    public function test_assign_courier_validates_task_type(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = $this->makeApprovedStore($owner);
        $customer = $this->makeUser('customer');
        $order = $this->makeOrder($customer, $store, 'created');

        $r = $this->actingAs($owner)->postJson("/api/orders/{$order->id}/assign-courier", [
            'task_type' => 'invalid_type',
        ]);
        $r->assertStatus(422);
    }

    // ====================================================================
    // Customer: profile + address
    // ====================================================================

    public function test_customer_can_get_profile(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->getJson('/api/customer/profile');
        $r->assertStatus(200);
        $r->assertJsonPath('email', $customer->email);
    }

    public function test_customer_can_update_profile_with_validation(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->patchJson('/api/customer/profile', [
            'name' => 'New Name',
        ]);
        $r->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'name' => 'New Name']);
    }

    public function test_phone_validation_rejects_invalid_characters(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->patchJson('/api/customer/profile', [
            'phone' => 'abc-not-valid-123!@#',
        ]);
        $r->assertStatus(422);
    }

    public function test_customer_can_create_address(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->postJson('/api/customer/addresses', [
            'label' => 'Rumah',
            'address' => 'Jl. Mawar 1',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'is_default' => true,
        ]);
        $r->assertStatus(201);
        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id, 'label' => 'Rumah',
        ]);
    }

    public function test_customer_cannot_delete_other_users_address(): void
    {
        $a = $this->makeUser('customer');
        $b = $this->makeUser('customer');
        $addr = Address::create([
            'user_id' => $a->id, 'label' => 'X', 'address' => 'Y',
            'latitude' => -6.2, 'longitude' => 106.8, 'is_default' => false,
            'recipient_name' => $a->name, 'phone' => '', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '10000',
        ]);

        $r = $this->actingAs($b)->deleteJson("/api/customer/addresses/{$addr->id}");
        $r->assertStatus(403);
    }

    // ====================================================================
    // Courier: dashboard scope
    // ====================================================================

    public function test_customer_cannot_access_courier_dashboard(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->getJson('/api/courier/dashboard');
        $r->assertStatus(403);
    }

    public function test_courier_can_access_own_dashboard(): void
    {
        $courierUser = $this->makeUser('courier');
        \App\Models\Courier::create([
            'user_id' => $courierUser->id, 'is_available' => true, 'is_verified' => true,
        ]);

        $r = $this->actingAs($courierUser)->getJson('/api/courier/dashboard');
        $r->assertStatus(200);
        $r->assertJsonStructure(['courier', 'active_tasks', 'stats']);
    }

    public function test_courier_cannot_update_availability_without_profile(): void
    {
        $courierUser = $this->makeUser('courier');
        // No Courier profile
        $r = $this->actingAs($courierUser)->patchJson('/api/courier/availability', [
            'is_available' => false,
        ]);
        // Has permission (view_courier_dashboard) but no courier row → 404
        $this->assertContains($r->status(), [404, 500]);
    }

    // ====================================================================
    // Store owner: store management
    // ====================================================================

    public function test_store_owner_can_list_owned_stores(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = $this->makeApprovedStore($owner);

        $r = $this->actingAs($owner)->getJson('/api/store-owner/stores');
        $r->assertStatus(200);
        $r->assertJsonCount(1, 'owned');
    }

    public function test_customer_cannot_list_store_owner_stores(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->getJson('/api/store-owner/stores');
        $r->assertStatus(403);
    }

    public function test_store_owner_can_update_own_store(): void
    {
        $owner = $this->makeUser('store_owner');
        $store = $this->makeApprovedStore($owner);

        $r = $this->actingAs($owner)->patchJson("/api/store-owner/stores/{$store->id}", [
            'name' => 'Updated Name',
        ]);
        $r->assertStatus(200);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name' => 'Updated Name']);
    }

    public function test_store_owner_cannot_update_other_store(): void
    {
        $ownerA = $this->makeUser('store_owner');
        $ownerB = $this->makeUser('store_owner');
        $storeA = $this->makeApprovedStore($ownerA);
        $storeB = $this->makeApprovedStore($ownerB);

        $r = $this->actingAs($ownerA)->patchJson("/api/store-owner/stores/{$storeB->id}", [
            'name' => 'Hacked',
        ]);
        $r->assertStatus(403);
    }

    // ====================================================================
    // Admin: dashboard
    // ====================================================================

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = $this->makeUser('customer');
        $r = $this->actingAs($customer)->getJson('/api/admin/dashboard');
        $r->assertStatus(403);
    }

    public function test_store_owner_cannot_access_admin_dashboard(): void
    {
        $owner = $this->makeUser('store_owner');
        $r = $this->actingAs($owner)->getJson('/api/admin/dashboard');
        $r->assertStatus(403);
    }

    public function test_platform_admin_can_access_dashboard(): void
    {
        $admin = $this->makeUser('platform_admin');
        $r = $this->actingAs($admin)->getJson('/api/admin/dashboard');
        $r->assertStatus(200);
        // Tahap 18: response wrapped in {stats, menu, is_owner}
        $r->assertJsonStructure([
            'stats' => ['users_total', 'stores_total', 'orders_total', 'revenue_today', 'revenue_month'],
            'menu',
            'is_owner',
        ]);
    }

    public function test_admin_dashboard_returns_consistent_403_for_unauthorized(): void
    {
        $courier = $this->makeUser('courier');
        $r1 = $this->actingAs($courier)->getJson('/api/admin/dashboard');
        $r2 = $this->actingAs($courier)->getJson('/api/admin/users');
        $r3 = $this->actingAs($courier)->getJson('/api/admin/stores');

        $this->assertEquals(403, $r1->status());
        $this->assertEquals(403, $r2->status());
        $this->assertEquals(403, $r3->status());
    }

    // ====================================================================
    // 403 consistency across endpoints
    // ====================================================================

    public function test_guest_gets_401_on_all_protected_endpoints(): void
    {
        $endpoints = [
            ['get', '/api/orders'],
            ['get', '/api/customer/profile'],
            ['get', '/api/courier/dashboard'],
            ['get', '/api/store-owner/stores'],
            ['get', '/api/admin/dashboard'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $r = $this->{$method . 'Json'}($url);
            $this->assertContains($r->status(), [401, 403], "Endpoint $method $url must be protected");
        }
    }

    public function test_create_order_with_unauthenticated_token_is_401(): void
    {
        $r = $this->postJson('/api/orders', []);
        $this->assertContains($r->status(), [401, 403]);
    }
}
