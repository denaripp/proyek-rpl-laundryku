<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Enums\OrderStatus;
use App\Services\Order\OrderService;
use App\Services\Payment\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Create customers
        $this->customerA = User::factory()->create();
        $roleCustomer = \App\Models\Role::where('name', 'customer')->first();
        $this->customerA->roles()->attach($roleCustomer);

        $this->customerB = User::factory()->create();
        $this->customerB->roles()->attach($roleCustomer);

        // Create a regular store owner (not platform admin)
        $this->storeOwner = User::factory()->create();
        $roleStoreOwner = \App\Models\Role::where('name', 'store_owner')->first();
        $this->storeOwner->roles()->attach($roleStoreOwner);

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ]);

        // Service
        $this->service = Service::create(['name' => 'Laundry', 'is_active' => true]);
        $this->servicePrice = ServicePrice::create([
            'store_id' => $this->store->id,
            'service_id' => $this->service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        // Create orders for customer A and B
        $orderService = new OrderService();

        $this->orderA = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 5],
            ],
        ], $this->customerA);

        $this->orderB = $orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 5],
            ],
        ], $this->customerB);

        // Create payments for each order
        $this->paymentA = Payment::create([
            'order_id' => $this->orderA->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_a',
            'expires_at' => now()->addHour(),
        ]);

        $this->paymentB = Payment::create([
            'order_id' => $this->orderB->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_b',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_customer_a_can_view_own_payment()
    {
        $this->actingAs($this->customerA);

        $response = $this->getJson('/api/payments/' . $this->paymentA->id);

        $response->assertStatus(200);
        $response->assertJson(['id' => $this->paymentA->id]);
    }

    public function test_customer_a_cannot_view_customer_b_payment()
    {
        $this->actingAs($this->customerA);

        $response = $this->getJson('/api/payments/' . $this->paymentB->id);

        $response->assertStatus(403);
    }

    public function test_store_owner_can_view_store_payment()
    {
        $this->actingAs($this->storeOwner);

        $response = $this->getJson('/api/payments/' . $this->paymentA->id);

        $response->assertStatus(200);
        $response->assertJson(['id' => $this->paymentA->id]);
    }

    public function test_store_owner_cannot_view_payment_from_other_store()
    {
        // Create another store and payment
        $otherStore = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id, // same owner but we'll change later
            'status' => 'approved',
            'is_active' => true,
        ]);
        // But we need a different owner for this test
        $otherOwner = User::factory()->create();
        $otherStore->owner_user_id = $otherOwner->id;
        $otherStore->status = 'approved';
        $otherStore->is_active = true;
        $otherStore->service_radius_km = 10;
        $otherStore->save();

        // Add service price for other store
        $service = Service::firstOrCreate(['name' => 'Laundry', 'is_active' => true]);
        ServicePrice::create([
            'store_id' => $otherStore->id,
            'service_id' => $service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        $otherOrder = (new OrderService())->createOrder([
            'store_id' => $otherStore->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 5],
            ],
        ], $this->customerA);

        $otherPayment = Payment::create([
            'order_id' => $otherOrder->id,
            'payment_method' => 'online',
            'amount' => 50000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv_c',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->storeOwner);

        $response = $this->getJson('/api/payments/' . $otherPayment->id);

        $response->assertStatus(403);
    }

    public function test_platform_admin_with_permission_can_view_all()
    {
        $admin = User::factory()->create();
        $roleAdmin = \App\Models\Role::where('name', 'platform_admin')->first();
        $admin->roles()->attach($roleAdmin);
        // Refresh and load permissions from role
        $admin->refresh();
        $admin->load('roles.permissions');

        // Ensure role has the permission (seeder should provide it)
        $this->assertTrue($admin->hasPermissionTo('view_payment'), 'Admin does not have view_payment permission');

        $this->actingAs($admin);

        $response = $this->getJson('/api/payments/' . $this->paymentA->id);

        $response->assertStatus(200);
        $response->assertJson(['id' => $this->paymentA->id]);

        $response2 = $this->getJson('/api/payments/' . $this->paymentB->id);
        $response2->assertStatus(200);
    }

    public function test_webhook_secret_missing_in_production_throws_exception()
    {
        // Simulate production environment
        $this->app['env'] = 'production';
        config(['xendit.webhook_secret' => '']);
        config(['xendit.api_key' => 'dummy']);

        // Ensure service uses new config
        $service = new XenditService();

        // We need to bypass the signature check but we want to test the secret check
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Webhook secret not configured');

        // Call verifyWebhookSignature with empty secret
        $service->verifyWebhookSignature('payload', 'signature', 'webhook_id');
    }

    public function test_webhook_secret_missing_in_testing_is_allowed()
    {
        config(['app.env' => 'testing']);
        config(['xendit.webhook_secret' => '']);
        config(['xendit.api_key' => 'dummy']);
        \Illuminate\Support\Facades\Config::set('xendit.api_key', 'dummy');

        $service = new XenditService();

        $result = $service->verifyWebhookSignature('payload', 'signature', 'webhook_id');

        $this->assertTrue($result);
    }
}