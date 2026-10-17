<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourierFactory extends Factory
{
    protected $model = Courier::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'store_id' => null, // not always assigned to a store
            'vehicle_type' => $this->faker->randomElement(['motorcycle', 'car', 'bicycle']),
            'vehicle_plate' => $this->faker->regexify('[A-Z]{1,2}[0-9]{1,4}[A-Z]{1,3}'),
            'is_verified' => true,
            'is_available' => true,
            'current_latitude' => $this->faker->latitude,
            'current_longitude' => $this->faker->longitude,
            'rating' => $this->faker->randomFloat(2, 1, 5),
        ];
    }
}