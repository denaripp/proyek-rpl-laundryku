<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        $superAdminRole = Role::firstOrCreate(['id' => 4, 'code' => 'super_admin'], ['name' => 'Super Admin']);
        $customerRole = Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);

        // Create active Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin@laundryku.id',
            'phone' => '+6281100000001',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->superAdmin->id,
            'role_id' => $superAdminRole->id,
            'status' => 'active',
        ]);

        // Create active Customer
        $this->customer = User::create([
            'name' => 'Customer Test',
            'email' => 'customer@laundryku.id',
            'phone' => '+6281200000002',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $this->customer->id,
            'role_id' => $customerRole->id,
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'superadmin@laundryku.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'roles',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $roles = $response->json('data.user.roles');
        $this->assertContains('super_admin', $roles);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'superadmin@laundryku.id',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    public function test_login_fails_with_unregistered_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'channel' => 'email',
            'destination' => 'nonexistent@laundryku.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    public function test_super_admin_can_access_protected_admin_routes_with_token(): void
    {
        $token = $this->superAdmin->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_customer_cannot_access_protected_admin_routes(): void
    {
        $token = $this->customer->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    public function test_unauthenticated_request_to_admin_route_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(401);
    }
}
