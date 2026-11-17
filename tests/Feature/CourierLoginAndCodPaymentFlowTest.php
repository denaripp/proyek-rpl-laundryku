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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierLoginAndCodPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $courierUser;
    protected Courier $courier;
    protected Store $store;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $courierRole = Role::where('name', 'courier')->first();

        $this->store = Store::factory()->create([
            'name' => 'Berkah Laundry',
            'is_active' => true,
            'status' => 'approved',
            'cod_enabled' => true,
        ]);

        $this->courierUser = User::factory()->create([
            'name' => 'Bang Kurir',
            'email' => 'kurir.test@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);

        if ($courierRole) {
            $this->courierUser->roles()->attach($courierRole->id);
        }

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 ABC',
            'is_verified' => true,
            'is_available' => true,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Budi Customer',
            'email' => 'customer.test@laundryku.test',
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
    }

    public function test_courier_can_login_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/kurir/login', [
            'email' => 'kurir.test@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'kurir.test@laundryku.test')
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_courier_can_login_with_phone_number(): void
    {
        $response = $this->postJson('/api/v1/auth/kurir/login', [
            'email' => '083150684755',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'kurir.test@laundryku.test')
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_customer_portal_login_with_courier_account_returns_helpful_invalid_portal_redirect(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'kurir.test@laundryku.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'INVALID_PORTAL')
            ->assertJsonPath('error.portal', 'courier')
            ->assertJsonPath('error.portal_url', '/courier/login');
    }

    public function test_customer_can_choose_cod_payment_and_order_moves_to_processing(): void
    {
        Sanctum::actingAs($this->customer, ['role:customer']);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::PAYMENT_REQUIRED->value,
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'method' => 'cod',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.method', 'cod')
            ->assertJsonPath('data.status', 'pending');

        $order->refresh();
        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING->value, $order->status);
    }

    public function test_courier_can_confirm_cod_and_order_completes(): void
    {
        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'courier_id' => $this->courierUser->id,
            'status' => OrderStatus::OUT_FOR_DELIVERY->value,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'total_amount' => 50000,
        ]);

        CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'delivery',
            'status' => CourierTaskStatus::ACCEPTED->value,
        ]);

        $response = $this->postJson("/api/v1/courier/tasks/{$order->id}/confirm-cod");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Pembayaran COD pesanan berhasil dikonfirmasi.');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertEquals(OrderStatus::COMPLETED->value, $order->status);
        $this->assertNotNull($order->completed_at);
    }
}
