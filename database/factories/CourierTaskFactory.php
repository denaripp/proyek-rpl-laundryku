<?php

namespace Database\Factories;

use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourierTaskFactory extends Factory
{
    protected $model = CourierTask::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'courier_id' => Courier::factory(),
            'task_type' => $this->faker->randomElement(['pickup', 'delivery']),
            'status' => 'assigned',
            'assigned_at' => now(),
            'accepted_at' => null,
            'arrived_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}