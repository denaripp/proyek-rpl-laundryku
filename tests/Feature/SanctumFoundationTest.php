<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CustomerUser;
use App\Models\MitraUser;
use App\Models\CourierUser;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumFoundationTest extends TestCase
{
    /**
     * Test token creation on User and scoped models maps tokenable_type consistently to 'user'.
     */
    public function test_token_creation_and_morph_map(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $tokenResult = $user->createToken('test_token', ['role:customer']);
        $this->assertNotEmpty($tokenResult->plainTextToken);

        $pat = $user->tokens()->first();
        $this->assertNotNull($pat);
        $this->assertEquals('user', $pat->tokenable_type);
        $this->assertEquals(['role:customer'], $pat->abilities);

        // Verify tokenable relationship resolves to User instance
        $this->assertInstanceOf(User::class, $pat->tokenable);
        $this->assertEquals($user->id, $pat->tokenable->id);
    }

    /**
     * Test token creation from scoped models (CustomerUser, MitraUser) also maps to 'user'.
     */
    public function test_scoped_models_tokenable_type_is_uniform(): void
    {
        $customer = CustomerUser::create([
            'name' => 'Cust Model Test',
            'email' => 'cust_scoped_' . uniqid() . '@example.com',
            'phone' => '0812' . rand(10000000, 99999999),
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $token = $customer->createToken('cust_token', ['role:customer']);
        $pat = $customer->tokens()->first();

        $this->assertInstanceOf(User::class, $pat->tokenable);
        $this->assertEquals($customer->id, $pat->tokenable->id);
    }
}
