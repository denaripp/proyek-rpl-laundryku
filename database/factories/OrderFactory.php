<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'customer_id' => User::factory(),
            'store_id' => Store::factory(),
            'status' => $this->faker->randomElement(['created', 'pending', 'processing', 'completed']),
            'total_amount' => $this->faker->randomFloat(2, 10000, 500000),
            'payment_status' => 'pending',
            'courier_id' => null,
            'order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'weight' => $this->faker->randomFloat(2, 1, 10),
            'total_price' => $this->faker->randomFloat(2, 10000, 500000),
            'order_date' => now(),
        ];
    }
}