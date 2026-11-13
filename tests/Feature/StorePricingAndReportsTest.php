<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorePricingAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $storeAdminRole = Role::where('name', 'store_admin')->first();
        $this->owner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Pemilik Toko Laundry Test',
        ]);
        if ($storeAdminRole) {
            $this->owner->roles()->sync([$storeAdminRole->id]);
        }

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Toko Laundry Test',
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_store_admin_can_get_default_pricing_rules(): void
    {
        Sanctum::actingAs($this->owner, ['role:store_admin']);

        $response = $this->getJson('/api/v1/store/pricing-rules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.min_weight_discount_kg', 5)
            ->assertJsonPath('data.is_promo_active', false);
    }

    public function test_store_admin_can_update_pricing_rules(): void
    {
        Sanctum::actingAs($this->owner, ['role:store_admin']);

        $payload = [
            'min_weight_discount_kg' => 7,
            'min_weight_discount_percent' => 15,
            'minimum_order_amount' => 20000,
            'express_surcharge_amount' => 10000,
            'is_promo_active' => true,
        ];

        $response = $this->putJson('/api/v1/store/pricing-rules', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.min_weight_discount_kg', 7)
            ->assertJsonPath('data.min_weight_discount_percent', 15)
            ->assertJsonPath('data.is_promo_active', true);

        // Verify retrieval
        $getRes = $this->getJson('/api/v1/store/pricing-rules');
        $getRes->assertStatus(200)
            ->assertJsonPath('data.min_weight_discount_percent', 15);
    }

    public function test_store_admin_can_get_reports(): void
    {
        Sanctum::actingAs($this->owner, ['role:store_admin']);

        $customer = User::factory()->create();

        // Create an order & payment
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'total_amount' => 50000,
            'final_price' => 50000,
            'payment_method' => 'qris',
            'status' => 'completed',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'payment_method' => 'qris',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/store/reports?from=' . now()->subDays(7)->toDateString() . '&to=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revenue_total', 50000)
            ->assertJsonPath('data.revenue_online', 50000)
            ->assertJsonPath('data.order_count', 1)
            ->assertJsonPath('data.completed_count', 1);
    }
}
