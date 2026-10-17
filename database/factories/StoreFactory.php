<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition()
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => $this->faker->company,
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->paragraph,
            'phone' => $this->faker->phoneNumber,
            'whatsapp' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'postal_code' => $this->faker->postcode,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'is_active' => true,
            'status' => 'approved',
            'cod_enabled' => false,
            'operational_hours' => json_encode(['monday' => '09:00-17:00']),
            'service_radius_km' => 10,
        ];
    }
}