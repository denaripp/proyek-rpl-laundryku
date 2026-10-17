<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Order;
use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => User::factory(),
            'store_id' => Store::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->sentence,
            'is_visible' => true,
            'moderated_at' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
        ];
    }
}