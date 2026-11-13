<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected User $customer;
    protected User $admin;
    protected Store $store;
    protected Service $service;
    protected ServicePrice $servicePrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);



        $this->orderService = new OrderService();

        $this->customer = User::factory()->create();
        $roleCustomer = \App\Models\Role::where('name', 'customer')->first();
        $this->customer->roles()->attach($roleCustomer);

        $this->admin = User::factory()->create();
        $roleAdmin = \App\Models\Role::where('name', 'platform_admin')->first();
        $this->admin->roles()->attach($roleAdmin);
        // Refresh the user to load the role and permissions from seeder
        $this->admin->refresh();
        $this->admin->load('roles.permissions');
        // Assert that permission is attached (debugging)
        $hasPerm = $this->admin->hasPermissionTo('update_order');
        $this->assertTrue($hasPerm, 'Admin does not have update_order permission');

        $this->store = Store::factory()->create([
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ]);

        $this->service = Service::create(['name' => 'Laundry', 'is_active' => true]);

        $this->servicePrice = ServicePrice::create([
            'store_id' => $this->store->id,
            'service_id' => $this->service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);
    }

    public function test_created_order_cannot_skip_to_completed()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid transition from created to completed.');
        $this->orderService->transition($order, OrderStatus::COMPLETED->value, $this->admin);
    }

    public function test_estimated_and_actual_weight_do_not_overwrite()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        // Transition through valid states to WEIGHED
        $this->orderService->transition($order, OrderStatus::WAITING_PICKUP->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::COURIER_ASSIGNED->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::COURIER_TO_CUSTOMER->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::PICKED_UP->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::IN_TRANSIT_TO_STORE->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::RECEIVED_BY_STORE->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::WEIGHED->value, $this->admin);

        // Set actual weight
        $this->orderService->setActualWeight($order, 7, $this->admin);

        $order->refresh();
        $this->assertEquals(5, $order->estimated_weight);
        $this->assertEquals(7, $order->actual_weight);
    }

    public function test_final_price_is_calculated_server_side()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        // Manually set actual weight and recalculate
        $order->actual_weight = 7;
        $this->orderService->recalculateFinalPrice($order);

        $order->refresh();
        $expectedPrice = (7 * $this->servicePrice->price_per_unit) + ($order->platform_fee ?? 0);
        $this->assertEquals($expectedPrice, $order->total_amount);
    }

    public function test_weighed_to_awaiting_price_approval_transition()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        // Transition through valid states to WEIGHED
        $this->orderService->transition($order, OrderStatus::WAITING_PICKUP->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::COURIER_ASSIGNED->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::COURIER_TO_CUSTOMER->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::PICKED_UP->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::IN_TRANSIT_TO_STORE->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::RECEIVED_BY_STORE->value, $this->admin);
        $this->orderService->transition($order, OrderStatus::WEIGHED->value, $this->admin);

        // Transition to awaiting_price_approval
        $order = $this->orderService->transition($order, 'awaiting_price_approval', $this->admin);
        $this->assertEquals('awaiting_price_approval', $order->status);
    }

    public function test_cannot_cancel_completed_order()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        // Transition to COMPLETED (simulate full flow)
        $order->status = OrderStatus::COMPLETED->value;
        $order->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order cannot be cancelled.');
        $this->orderService->cancel($order, $this->customer, 'Test reason');
    }

    public function test_cancellation_requires_reason_for_processing()
    {
        $order = $this->orderService->createOrder([
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ], $this->customer);

        // Transition to PROCESSING
        $order->status = OrderStatus::PROCESSING->value;
        $order->save();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cancellation reason is required for processing orders.');
        $this->orderService->cancel($order, $this->customer, '');
    }
}