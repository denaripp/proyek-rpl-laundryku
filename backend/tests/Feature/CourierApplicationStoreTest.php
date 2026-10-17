<?php

namespace Tests\Feature;

use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourierApplicationStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $applicant;
    protected string $applicantToken;

    protected User $ownerA;
    protected string $ownerAToken;
    protected Store $storeA;

    protected User $ownerB;
    protected string $ownerBToken;
    protected Store $storeB;

    protected Role $customerRole;
    protected Role $courierRole;
    protected Role $storeAdminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->customerRole = Role::where('code', 'customer')->first();
        $this->courierRole = Role::where('code', 'courier')->first();
        $this->storeAdminRole = Role::where('code', 'store_admin')->first();

        // 1. Applicant (Customer)
        $this->applicant = User::create([
            'name' => 'Calon Kurir Rudi',
            'email' => 'rudi@courier.id',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->applicant->id]);
        UserRole::create(['user_id' => $this->applicant->id, 'role_id' => $this->customerRole->id, 'status' => 'active']);
        $this->applicantToken = $this->applicant->createToken('auth_token')->plainTextToken;

        // 2. Owner A & Store A (Approved & Active)
        $this->ownerA = User::create([
            'name' => 'Owner Store A',
            'email' => 'owner.a@laundry.id',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->ownerA->id]);
        $this->storeA = Store::create([
            'owner_user_id' => $this->ownerA->id,
            'name' => 'Laundry Bersih Store A',
            'address_text' => 'Jl. Toko A No. 1, Bandung',
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'service_radius_km' => 5.0,
        ]);
        UserRole::create([
            'user_id' => $this->ownerA->id,
            'role_id' => $this->storeAdminRole->id,
            'store_id' => $this->storeA->id,
            'status' => 'active',
        ]);
        $this->ownerAToken = $this->ownerA->createToken('auth_token')->plainTextToken;

        // 3. Owner B & Store B (Approved & Active)
        $this->ownerB = User::create([
            'name' => 'Owner Store B',
            'email' => 'owner.b@laundry.id',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->ownerB->id]);
        $this->storeB = Store::create([
            'owner_user_id' => $this->ownerB->id,
            'name' => 'Laundry Mantap Store B',
            'address_text' => 'Jl. Toko B No. 2, Bandung',
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'service_radius_km' => 5.0,
        ]);
        UserRole::create([
            'user_id' => $this->ownerB->id,
            'role_id' => $this->storeAdminRole->id,
            'store_id' => $this->storeB->id,
            'status' => 'active',
        ]);
        $this->ownerBToken = $this->ownerB->createToken('auth_token')->plainTextToken;
    }

    public function test_submit_courier_without_store_is_rejected(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        $response = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_submit_courier_with_invalid_or_nonexistent_store_is_rejected(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        $response = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => '00000000-0000-0000-0000-000000000000',
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_submit_courier_with_unapproved_or_inactive_store_is_rejected(): void
    {
        $pendingStore = Store::create([
            'owner_user_id' => $this->ownerA->id,
            'name' => 'Laundry Belum Approved',
            'address_text' => 'Jl. Pending No. 1',
            'approval_status' => 'pending',
            'operational_status' => 'inactive',
            'service_radius_km' => 5.0,
        ]);

        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        $response = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $pendingStore->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'STORE_NOT_ELIGIBLE',
                ],
            ]);
    }

    public function test_successful_courier_application_sets_store_id_and_returns_target_store_in_status(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        $response = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->applicant->id,
                    'store_id' => $this->storeA->id,
                    'verification_status' => 'pending',
                ],
            ]);

        // Status endpoint returns target store details and hides sensitive data
        $statusRes = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->getJson('/api/v1/upgrades/courier');

        $statusRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'application' => [
                        'store_id' => $this->storeA->id,
                        'store_name' => 'Laundry Bersih Store A',
                        'verification_status' => 'pending',
                    ],
                ],
            ]);

        $appData = $statusRes->json('data.application');
        $this->assertArrayNotHasKey('identity_encrypted', $appData);
        $this->assertArrayNotHasKey('vehicle_plate_encrypted', $appData);
        $this->assertArrayNotHasKey('nik', $appData);
        $this->assertArrayNotHasKey('vehicle_plate', $appData);
        $this->assertArrayNotHasKey('ktp_storage_key', $appData);
    }

    public function test_double_pending_courier_application_is_rejected(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        // Attempt second application to store B while first is pending
        $secondRes = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeB->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $secondRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_APPLICATION',
                ],
            ]);
    }

    public function test_store_admin_can_only_see_candidates_who_applied_to_their_store(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        // Rudi applies to Store A
        $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        // Reset auth guards for new token
        $this->app['auth']->forgetGuards();

        // Owner A views couriers / candidates -> Should see Rudi
        $resStoreA = $this->withHeader('Authorization', "Bearer {$this->ownerAToken}")
            ->getJson('/api/v1/store-admin/couriers');

        $resStoreA->assertStatus(200);
        $candidatesA = $resStoreA->json('data.available_candidates');
        $this->assertCount(1, $candidatesA);
        $this->assertEquals('Calon Kurir Rudi', $candidatesA[0]['name']);

        // Reset auth guards for owner B
        $this->app['auth']->forgetGuards();

        // Owner B views couriers / candidates -> Should NOT see Rudi (empty)
        $resStoreB = $this->withHeader('Authorization', "Bearer {$this->ownerBToken}")
            ->getJson('/api/v1/store-admin/couriers');

        $resStoreB->assertStatus(200);
        $candidatesB = $resStoreB->json('data.available_candidates');
        $this->assertCount(0, $candidatesB);
    }

    public function test_store_admin_b_cannot_recruit_or_reject_applicant_of_store_a(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        // Rudi applies to Store A
        $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $profile = CourierProfile::where('user_id', $this->applicant->id)->first();
        $this->assertNotNull($profile);

        // Reset auth guards for owner B
        $this->app['auth']->forgetGuards();

        // Store B admin attempts to recruit Rudi -> 403 Forbidden
        $recruitRes = $this->withHeader('Authorization', "Bearer {$this->ownerBToken}")
            ->postJson("/api/v1/store-admin/couriers/recruit/{$profile->id}");

        $recruitRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED_STORE_ACTION',
                ],
            ]);

        // Reset auth guards
        $this->app['auth']->forgetGuards();

        // Store B admin attempts to reject Rudi -> 403 Forbidden
        $rejectRes = $this->withHeader('Authorization', "Bearer {$this->ownerBToken}")
            ->postJson("/api/v1/store-admin/couriers/reject/{$profile->id}", [
                'reason' => 'Bukan pelamar toko kami',
            ]);

        $rejectRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED_STORE_ACTION',
                ],
            ]);
    }

    public function test_store_admin_a_can_approve_applicant_creating_active_assignment(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        // Rudi applies to Store A
        $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $profile = CourierProfile::where('user_id', $this->applicant->id)->first();

        // Reset auth guards for owner A
        $this->app['auth']->forgetGuards();

        // Owner A approves Rudi
        $recruitRes = $this->withHeader('Authorization', "Bearer {$this->ownerAToken}")
            ->postJson("/api/v1/store-admin/couriers/recruit/{$profile->id}");

        $recruitRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'active',
                    'store_id' => $this->storeA->id,
                ],
            ]);

        // Verify CourierProfile is approved and active
        $profile->refresh();
        $this->assertEquals('approved', $profile->verification_status);
        $this->assertEquals('active', $profile->status);

        // Verify active assignment created
        $assignment = CourierStoreAssignment::where('courier_id', $profile->id)
            ->where('store_id', $this->storeA->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('active', $assignment->status);

        // Verify user_role is active
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->applicant->id,
            'role_id' => $this->courierRole->id,
            'store_id' => $this->storeA->id,
            'status' => 'active',
        ]);
    }

    public function test_store_admin_a_can_reject_applicant_with_reason(): void
    {
        $ktpFile = UploadedFile::fake()->image('ktp_rudi.jpg', 400, 300);

        // Rudi applies to Store A
        $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $this->storeA->id,
                'nik' => '3201123456780099',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 9999 XYZ',
                'ktp_file' => $ktpFile,
            ]);

        $profile = CourierProfile::where('user_id', $this->applicant->id)->first();

        // Reset auth guards for owner A
        $this->app['auth']->forgetGuards();

        // Owner A rejects Rudi
        $rejectRes = $this->withHeader('Authorization', "Bearer {$this->ownerAToken}")
            ->postJson("/api/v1/store-admin/couriers/reject/{$profile->id}", [
                'reason' => 'Kuota kurir untuk outlet ini sudah penuh.',
            ]);

        $rejectRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'verification_status' => 'rejected',
                    'rejection_reason' => 'Kuota kurir untuk outlet ini sudah penuh.',
                ],
            ]);

        // Verify CourierProfile is rejected
        $profile->refresh();
        $this->assertEquals('rejected', $profile->verification_status);
        $this->assertEquals('inactive', $profile->status);
        $this->assertEquals('Kuota kurir untuk outlet ini sudah penuh.', $profile->rejection_reason);

        // Verify no active assignment
        $assignment = CourierStoreAssignment::where('courier_id', $profile->id)->first();
        $this->assertNull($assignment);

        // Verify user_role is rejected and not active
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->applicant->id,
            'role_id' => $this->courierRole->id,
            'status' => 'rejected',
        ]);

        // Reset auth guards for applicant
        $this->app['auth']->forgetGuards();

        // Verify applicant can view rejection reason via status endpoint
        $statusRes = $this->withHeader('Authorization', "Bearer {$this->applicantToken}")
            ->getJson('/api/v1/upgrades/courier');

        $statusRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'application' => [
                        'verification_status' => 'rejected',
                        'rejection_reason' => 'Kuota kurir untuk outlet ini sudah penuh.',
                    ],
                ],
            ]);
    }
}
