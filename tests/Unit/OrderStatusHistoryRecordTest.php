<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OrderStatusHistoryRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_creates_history_entry_with_order_model()
    {
        $actor = User::factory()->create(['role' => User::ROLE_COURIER]);
        $order = Order::factory()->create(['status' => 'pending_pickup']);

        $history = OrderStatusHistory::record(
            order: $order,
            status: 'picked_up',
            previousStatus: 'pending_pickup',
            actor: $actor,
            reason: 'Courier picked up order',
            metadata: ['test_key' => 'test_value']
        );

        $this->assertNotNull($history);
        $this->assertEquals($order->id, $history->order_id);
        $this->assertEquals('picked_up', $history->status);
        $this->assertEquals('pending_pickup', $history->previous_status);
        $this->assertEquals($actor->id, $history->actor_id);
        $this->assertEquals(User::ROLE_COURIER, $history->actor_role);
        $this->assertEquals('Courier picked up order', $history->reason);
        $this->assertEquals(['test_key' => 'test_value'], $history->metadata);
    }

    public function test_record_creates_history_entry_with_order_id_and_defaults()
    {
        $actor = User::factory()->create(['role' => User::ROLE_STORE_ADMIN]);
        $order = Order::factory()->create(['status' => 'created']);

        $history = OrderStatusHistory::record(
            order: $order->id,
            status: 'at_store',
            previousStatus: null,
            actor: $actor
        );

        $this->assertNotNull($history);
        $this->assertEquals($order->id, $history->order_id);
        $this->assertEquals('at_store', $history->status);
        $this->assertEquals('created', $history->previous_status);
        $this->assertEquals($actor->id, $history->actor_id);
    }

    public function test_record_catches_exception_and_logs_warning()
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to record order status history');
            });

        // Passing an array as order to force an exception in Eloquent create
        $result = OrderStatusHistory::record(
            order: ['invalid'],
            status: 'cancelled'
        );

        $this->assertNull($result);
    }
}
