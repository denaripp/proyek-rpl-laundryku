<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Role;
use App\Enums\StoreStatus;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class StoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected StoreService $storeService;
    protected User $admin;
    protected User $customer;
    protected User $storeOwner;
    protected User $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->storeService = new StoreService();

        $this->admin = User::factory()->create();
        $roleAdmin = Role::where('name', 'platform_admin')->first();
        $this->admin->roles()->attach($roleAdmin);

        $this->customer = User::factory()->create();
        $roleCustomer = Role::where('name', 'customer')->first();
        $this->customer->roles()->attach($roleCustomer);

        $this->storeOwner = User::factory()->create();
        $roleOwner = Role::where('name', 'store_owner')->first();
        $this->storeOwner->roles()->attach($roleOwner);

        $this->courier = User::factory()->create();
        $roleCourier = Role::where('name', 'courier')->first();
        $this->courier->roles()->attach($roleCourier);
    }

    public function test_unauthorized_role_cannot_approve_store()
    {
        $store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => StoreStatus::UNDER_REVIEW->value,
        ]);

        // Customer tries to approve
        $this->actingAs($this->customer);
        $this->expectException(AuthorizationException::class);
        $this->storeService->approve($store, $this->customer, 'Good store');

        // Store owner tries to approve (own store)
        $this->actingAs($this->storeOwner);
        $this->expectException(AuthorizationException::class);
        $this->storeService->approve($store, $this->storeOwner, 'Good store');

        // Courier tries to approve
        $this->actingAs($this->courier);
        $this->expectException(AuthorizationException::class);
        $this->storeService->approve($store, $this->courier, 'Good store');

        // Admin can approve
        $this->actingAs($this->admin);
        $approved = $this->storeService->approve($store, $this->admin, 'Good store');
        $this->assertEquals(StoreStatus::APPROVED->value, $approved->fresh()->status);
    }

    public function test_unauthorized_role_cannot_reject_store()
    {
        $store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => StoreStatus::UNDER_REVIEW->value,
        ]);

        $this->actingAs($this->customer);
        $this->expectException(AuthorizationException::class);
        $this->storeService->reject($store, $this->customer, 'Bad store');

        $this->actingAs($this->storeOwner);
        $this->expectException(AuthorizationException::class);
        $this->storeService->reject($store, $this->storeOwner, 'Bad store');

        $this->actingAs($this->courier);
        $this->expectException(AuthorizationException::class);
        $this->storeService->reject($store, $this->courier, 'Bad store');

        $this->actingAs($this->admin);
        $rejected = $this->storeService->reject($store, $this->admin, 'Bad store');
        $this->assertEquals(StoreStatus::REJECTED->value, $rejected->fresh()->status);
    }

    public function test_store_owner_cannot_approve_own_store()
    {
        $store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => StoreStatus::UNDER_REVIEW->value,
        ]);

        $this->actingAs($this->storeOwner);
        $this->expectException(AuthorizationException::class);
        $this->storeService->approve($store, $this->storeOwner, 'Good store');
    }
}