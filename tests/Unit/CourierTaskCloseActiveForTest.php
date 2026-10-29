<?php

namespace Tests\Unit;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierTaskCloseActiveForTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_active_for_pickup_tasks()
    {
        $store = Store::factory()->create();
        $order = Order::factory()->create(['store_id' => $store->id]);
        $courier = Courier::factory()->create(['store_id' => $store->id]);

        $task = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        $affected = CourierTask::closeActiveFor($order, 'pickup');

        $this->assertEquals(1, $affected);
        $task->refresh();
        $this->assertEquals('delivered', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_close_active_for_delivery_tasks_leaves_pickup_untouched()
    {
        $store = Store::factory()->create();
        $order = Order::factory()->create(['store_id' => $store->id]);
        $courier = Courier::factory()->create(['store_id' => $store->id]);

        $pickupTask = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        $deliveryTask = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'delivery',
            'status' => 'accepted',
        ]);

        $affected = CourierTask::closeActiveFor($order, 'delivery');

        $this->assertEquals(1, $affected);

        $deliveryTask->refresh();
        $this->assertEquals('delivered', $deliveryTask->status);
        $this->assertNotNull($deliveryTask->completed_at);

        $pickupTask->refresh();
        $this->assertEquals('accepted', $pickupTask->status);
        $this->assertNull($pickupTask->completed_at);
    }

    public function test_close_active_for_does_not_touch_already_completed_tasks()
    {
        $store = Store::factory()->create();
        $order = Order::factory()->create(['store_id' => $store->id]);
        $courier = Courier::factory()->create(['store_id' => $store->id]);

        $existingTime = now()->subHours(2);
        $task = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'task_type' => 'pickup',
            'status' => 'delivered',
            'completed_at' => $existingTime,
        ]);

        $affected = CourierTask::closeActiveFor($order, 'pickup');

        $this->assertEquals(0, $affected);
        $task->refresh();
        $this->assertEquals('delivered', $task->status);
        $this->assertEquals($existingTime->toDateTimeString(), $task->completed_at->toDateTimeString());
    }
}
