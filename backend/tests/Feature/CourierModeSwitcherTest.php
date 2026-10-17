<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierModeSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected User $courierUser;
    protected User $normalUser;
    protected Store $store;
    protected CourierProfile $courierProfile;
    protected CourierStoreAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $courierRole = Role::where('code', 'courier')->first();
        $customerRole = Role::where('code', 'customer')->first();

        // 1. Owner & Store
        $owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@test.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        $this->store = Store::create([
            'owner_user_id' => $owner->id,
            'name' => 'Laundry Bersih',
            'address_text' => 'Jl. Merdeka 10',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // 2. Courier User
        $this->courierUser = User::create([
            'name' => 'Cellos Kurir',
            'email' => 'cellos@test.com',
            'status' => 'active',
            'verified_at' => now(),
            'active_mode' => 'courier',
        ]);
        UserProfile::create(['user_id' => $this->courierUser->id]);
        UserRole::create(['user_id' => $this->courierUser->id, 'role_id' => $courierRole->id, 'status' => 'active']);

        $this->courierProfile = CourierProfile::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'identity_encrypted' => '3201010101010099',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_encrypted' => 'B 1234 XYZ',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $this->assignment = CourierStoreAssignment::create([
            'courier_id' => $this->courierProfile->id,
            'store_id' => $this->store->id,
            'recruited_by' => $owner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // 3. Normal User (no courier assignment)
        $this->normalUser = User::create([
            'name' => 'Customer Biasa',
            'email' => 'normal@test.com',
            'status' => 'active',
            'verified_at' => now(),
            'active_mode' => 'customer',
        ]);
        UserProfile::create(['user_id' => $this->normalUser->id]);
        UserRole::create(['user_id' => $this->normalUser->id, 'role_id' => $customerRole->id, 'status' => 'active']);
    }

    /**
     * Test 1: User tanpa penugasan kurir aktif tidak bisa beralih ke mode courier.
     */
    public function test_user_without_courier_assignment_cannot_switch_to_courier(): void
    {
        Sanctum::actingAs($this->normalUser);

        $res = $this->postJson('/api/v1/me/mode', ['mode' => 'courier']);
        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);

        $this->assertEquals('customer', $this->normalUser->fresh()->active_mode);
    }

    /**
     * Test 2: Kurir bisa beralih dari courier ke customer, lalu kembali ke courier.
     * Audit log USER_MODE_SWITCHED dicatat dengan metadata from dan to.
     */
    public function test_courier_can_switch_to_customer_and_back_with_audit_log(): void
    {
        Sanctum::actingAs($this->courierUser);

        // Switch to customer
        $res1 = $this->postJson('/api/v1/me/mode', ['mode' => 'customer']);
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_mode' => 'customer',
                ],
            ]);
        $this->assertEquals('customer', $this->courierUser->fresh()->active_mode);

        // Check audit log
        $log1 = AuditLog::where('action', 'USER_MODE_SWITCHED')
            ->where('actor_id', $this->courierUser->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log1);
        $this->assertEquals('courier', $log1->metadata['from']);
        $this->assertEquals('customer', $log1->metadata['to']);

        // Switch back to courier
        $res2 = $this->postJson('/api/v1/me/mode', ['mode' => 'courier']);
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_mode' => 'courier',
                ],
            ]);
        $this->assertEquals('courier', $this->courierUser->fresh()->active_mode);

        $log2 = AuditLog::where('action', 'USER_MODE_SWITCHED')
            ->where('actor_id', $this->courierUser->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log2);
        $this->assertEquals('customer', $log2->metadata['from']);
        $this->assertEquals('to', 'to');
        $this->assertEquals('courier', $log2->metadata['to']);
    }

    /**
     * Test 3: /api/v1/courier/tasks ditolak 403 saat active_mode adalah customer.
     */
    public function test_courier_endpoint_rejected_when_active_mode_is_customer(): void
    {
        $this->courierUser->update(['active_mode' => 'customer']);
        Sanctum::actingAs($this->courierUser);

        $res = $this->getJson('/api/v1/courier/tasks');
        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                ],
            ]);
    }

    /**
     * Test 4: Setelah penugasan kurir dinonaktifkan, mode otomatis turun ke customer dengan 403 MODE_REVOKED.
     */
    public function test_when_assignment_is_ended_mode_downgrades_to_customer_with_mode_revoked(): void
    {
        $this->courierUser->update(['active_mode' => 'courier']);
        Sanctum::actingAs($this->courierUser);

        // End assignment
        $this->assignment->update(['status' => 'inactive', 'ended_at' => now()]);

        $res = $this->getJson('/api/v1/courier/tasks');
        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'MODE_REVOKED',
                ],
            ]);

        $this->assertEquals('customer', $this->courierUser->fresh()->active_mode);
    }
}
