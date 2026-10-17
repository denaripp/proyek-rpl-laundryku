<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $customerRole = Role::where('code', 'customer')->first();
        $roleId = $customerRole ? $customerRole->id : 1;

        // Create User A
        $this->userA = User::create([
            'name' => 'User Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->userA->id]);
        UserRole::create(['user_id' => $this->userA->id, 'role_id' => $roleId, 'status' => 'active']);
        $this->tokenA = $this->userA->createToken('auth_token')->plainTextToken;

        // Create User B
        $this->userB = User::create([
            'name' => 'User Bob',
            'email' => 'bob@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->userB->id]);
        UserRole::create(['user_id' => $this->userB->id, 'role_id' => $roleId, 'status' => 'active']);
        $this->tokenB = $this->userB->createToken('auth_token')->plainTextToken;
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->userA->id,
                    'name' => 'User Alice',
                    'email' => 'alice@example.com',
                ],
            ]);

        $updateRes = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->putJson('/api/v1/profile', [
                'name' => 'Alice Updated',
                'date_of_birth' => '1995-05-15',
            ]);

        $updateRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Alice Updated',
                    'profile' => [
                        'date_of_birth' => '1995-05-15',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', ['id' => $this->userA->id, 'name' => 'Alice Updated']);
        $this->assertEquals('1995-05-15', UserProfile::where('user_id', $this->userA->id)->first()->date_of_birth->format('Y-m-d'));
    }

    public function test_user_can_upload_and_stream_private_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $uploadRes = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $file,
            ]);

        $uploadRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_photo' => true,
                ],
            ]);

        $profile = UserProfile::where('user_id', $this->userA->id)->first();
        $this->assertNotNull($profile->profile_photo_key);
        Storage::disk('public')->assertExists($profile->profile_photo_key);

        // Stream photo
        $streamRes = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->get('/api/v1/profile/photo');

        $streamRes->assertStatus(200);
        $streamRes->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_profile_photo_rejects_illegal_file_extension_and_oversized_file(): void
    {
        // Illegal extension (.txt)
        $txtFile = UploadedFile::fake()->create('malicious.txt', 100);
        $resIllegal = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $txtFile,
            ]);

        $resIllegal->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);

        // Oversized file (> 5MB, e.g. 6000KB)
        $largeFile = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');
        $resLarge = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/profile/photo', [
                'photo' => $largeFile,
            ]);

        $resLarge->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_user_can_crud_own_addresses_and_set_default(): void
    {
        // 1. Create Address 1 (should automatically become default since it's the first)
        $res1 = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/addresses', [
                'label' => 'Rumah',
                'address_text' => 'Jl. Dago No. 10, Bandung',
                'latitude' => -6.8852,
                'longitude' => 107.6138,
            ]);

        $res1->assertStatus(201);
        $addr1Id = $res1->json('data.id');
        $this->assertTrue($res1->json('data.is_default'));

        // 2. Create Address 2 with is_default = true
        $res2 = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/addresses', [
                'label' => 'Kantor',
                'address_text' => 'Jl. Asia Afrika No. 50, Bandung',
                'latitude' => -6.9215,
                'longitude' => 107.6111,
                'is_default' => true,
            ]);

        $res2->assertStatus(201);
        $addr2Id = $res2->json('data.id');
        $this->assertTrue($res2->json('data.is_default'));

        // Assert Address 1 is no longer default
        $this->assertFalse(Address::find($addr1Id)->is_default);

        // 3. Set Address 1 back to default
        $resSetDefault = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson("/api/v1/addresses/{$addr1Id}/set-default");

        $resSetDefault->assertStatus(200);
        $this->assertTrue(Address::find($addr1Id)->is_default);
        $this->assertFalse(Address::find($addr2Id)->is_default);

        // 4. Update Address 2
        $resUpdate = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->putJson("/api/v1/addresses/{$addr2Id}", [
                'label' => 'Kantor Pusat',
            ]);

        $resUpdate->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'label' => 'Kantor Pusat',
                ],
            ]);

        // 5. Delete Address
        $resDelete = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->deleteJson("/api/v1/addresses/{$addr2Id}");

        $resDelete->assertStatus(200);
        $this->assertNull(Address::find($addr2Id));
    }

    public function test_user_cannot_access_or_modify_other_users_address(): void
    {
        // Create address belonging to User A
        $addressA = Address::create([
            'user_id' => $this->userA->id,
            'label' => 'Rumah Alice',
            'address_text' => 'Jl. Rahasia Alice',
            'is_default' => true,
        ]);

        // User B tries to view User A's address
        $resView = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/addresses/{$addressA->id}");
        $resView->assertStatus(403);

        // User B tries to update User A's address
        $resUpdate = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->putJson("/api/v1/addresses/{$addressA->id}", [
                'label' => 'Hacked',
            ]);
        $resUpdate->assertStatus(403);

        // User B tries to delete User A's address
        $resDelete = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->deleteJson("/api/v1/addresses/{$addressA->id}");
        $resDelete->assertStatus(403);

        // User B tries to set default on User A's address
        $resSet = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->postJson("/api/v1/addresses/{$addressA->id}/set-default");
        $resSet->assertStatus(403);
    }

    public function test_customer_can_apply_courier_upgrade_with_encrypted_fields(): void
    {
        $store = Store::create([
            'owner_user_id' => $this->userB->id,
            'name' => 'Laundry Mitra Alice',
            'address_text' => 'Jl. Mitra No. 1',
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'service_radius_km' => 5.0,
        ]);

        $nik = '3201123456780001';
        $plate = 'D 1234 ABC';
        $ktpFile = UploadedFile::fake()->image('ktp_alice.jpg', 400, 300);

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $store->id,
                'nik' => $nik,
                'vehicle_type' => 'motor',
                'vehicle_plate' => $plate,
                'ktp_file' => $ktpFile,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->userA->id,
                    'store_id' => $store->id,
                    'vehicle_type' => 'motor',
                    'verification_status' => 'pending',
                    'has_ktp' => true,
                ],
            ]);

        // Verify database encryption
        $courier = CourierProfile::where('user_id', $this->userA->id)->first();
        $this->assertNotNull($courier);
        $this->assertEquals($store->id, $courier->store_id);

        // Raw database values must be ciphertext (encrypted)
        $rawRecord = DB::table('courier_profiles')->where('id', $courier->id)->first();
        $this->assertNotEquals($nik, $rawRecord->identity_encrypted);
        $this->assertNotEquals($plate, $rawRecord->vehicle_plate_encrypted);

        // Decrypted model accessor returns true value
        $this->assertEquals($nik, $courier->identity_encrypted);
        $this->assertEquals($plate, $courier->vehicle_plate_encrypted);

        // Verify pending user_roles entry
        $courierRole = Role::where('code', 'courier')->first();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->userA->id,
            'role_id' => $courierRole->id,
            'store_id' => $store->id,
            'status' => 'pending',
        ]);
    }

    public function test_courier_nik_and_plate_are_hidden_from_general_responses(): void
    {
        $store = Store::create([
            'owner_user_id' => $this->userA->id,
            'name' => 'Laundry Mitra Bob',
            'address_text' => 'Jl. Mitra No. 2',
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'service_radius_km' => 5.0,
        ]);

        $nik = '3201123456780002';
        $plate = 'D 5678 DEF';
        $ktpFile = UploadedFile::fake()->image('ktp_bob.jpg', 400, 300);

        $applyRes = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $store->id,
                'nik' => $nik,
                'vehicle_type' => 'motor',
                'vehicle_plate' => $plate,
                'ktp_file' => $ktpFile,
            ]);

        $applyData = $applyRes->json('data');
        $this->assertArrayNotHasKey('identity_encrypted', $applyData);
        $this->assertArrayNotHasKey('vehicle_plate_encrypted', $applyData);
        $this->assertArrayNotHasKey('nik', $applyData);
        $this->assertArrayNotHasKey('vehicle_plate', $applyData);
        $this->assertArrayNotHasKey('ktp_storage_key', $applyData);

        // Status endpoint also hides sensitive data
        $statusRes = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson('/api/v1/upgrades/courier');

        $statusData = $statusRes->json('data.application');
        $this->assertArrayNotHasKey('identity_encrypted', $statusData);
        $this->assertArrayNotHasKey('vehicle_plate_encrypted', $statusData);
        $this->assertArrayNotHasKey('ktp_storage_key', $statusData);
    }

    public function test_duplicate_courier_upgrade_is_rejected(): void
    {
        $store = Store::create([
            'owner_user_id' => $this->userB->id,
            'name' => 'Laundry Mitra Dup',
            'address_text' => 'Jl. Mitra No. 3',
            'approval_status' => 'approved',
            'operational_status' => 'active',
            'service_radius_km' => 5.0,
        ]);

        $ktpFile = UploadedFile::fake()->image('ktp.jpg');

        $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $store->id,
                'nik' => '3201123456780001',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 1234 ABC',
                'ktp_file' => $ktpFile,
            ]);

        // Second submission while pending must be rejected
        $dupRes = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/courier', [
                'store_id' => $store->id,
                'nik' => '3201123456780001',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'D 1234 ABC',
                'ktp_file' => $ktpFile,
            ]);

        $dupRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_APPLICATION',
                ],
            ]);
    }

    public function test_customer_can_apply_store_upgrade_with_valid_radius(): void
    {
        $docFile = UploadedFile::fake()->create('nib_document.pdf', 500, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/store', [
                'name' => 'Laundry Alice Jaya',
                'description' => 'Laundry terpercaya',
                'address_text' => 'Jl. Merdeka No. 1, Bandung',
                'latitude' => -6.9000,
                'longitude' => 107.6000,
                'service_radius_km' => 8.50,
                'document_type' => 'nib',
                'document_file' => $docFile,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Laundry Alice Jaya',
                    'service_radius_km' => 8.50,
                    'approval_status' => 'pending',
                    'operational_status' => 'inactive',
                ],
            ]);

        $store = Store::where('owner_user_id', $this->userA->id)->first();
        $this->assertNotNull($store);

        // Verify document was recorded
        $doc = StoreDocument::where('store_id', $store->id)->first();
        $this->assertNotNull($doc);
        $this->assertEquals('nib', $doc->document_type);
        Storage::disk('local')->assertExists($doc->storage_key);

        // Verify store_admin role with pending status
        $storeAdminRole = Role::where('code', 'store_admin')->first();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->userA->id,
            'role_id' => $storeAdminRole->id,
            'store_id' => $store->id,
            'status' => 'pending',
        ]);
    }

    public function test_store_upgrade_rejects_radius_greater_than_10_km(): void
    {
        $docFile = UploadedFile::fake()->create('nib.pdf', 500, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/store', [
                'name' => 'Laundry Terlalu Jauh',
                'address_text' => 'Jl. Antah Berantah',
                'service_radius_km' => 12.50, // Exceeds 10 km limit
                'document_type' => 'nib',
                'document_file' => $docFile,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_duplicate_store_upgrade_is_rejected(): void
    {
        $docFile = UploadedFile::fake()->create('nib.pdf', 500, 'application/pdf');

        $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/store', [
                'name' => 'Laundry Pertama',
                'address_text' => 'Jl. Satu No. 1',
                'service_radius_km' => 5.0,
                'document_type' => 'nib',
                'document_file' => $docFile,
            ]);

        // Second submission while pending must be rejected
        $dupRes = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/upgrades/store', [
                'name' => 'Laundry Kedua',
                'address_text' => 'Jl. Dua No. 2',
                'service_radius_km' => 5.0,
                'document_type' => 'nib',
                'document_file' => $docFile,
            ]);

        $dupRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_APPLICATION',
                ],
            ]);
    }

    public function test_private_files_cannot_be_accessed_by_unauthorized_users(): void
    {
        // Store belonging to User A with private document
        $store = Store::create([
            'owner_user_id' => $this->userA->id,
            'name' => 'Toko Rahasia Alice',
            'address_text' => 'Jl. Rahasia',
            'service_radius_km' => 5.0,
        ]);

        $docPath = 'private/store_documents/secret.pdf';
        Storage::disk('local')->put($docPath, 'SECRET CONTENT');

        $doc = StoreDocument::create([
            'store_id' => $store->id,
            'document_type' => 'nib',
            'storage_key' => $docPath,
        ]);

        // User B attempts to access User A's store document -> FORBIDDEN (403)
        $resForbidden = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/documents/store/{$doc->id}");

        $resForbidden->assertStatus(403);

        // Reset auth guards cache so tokenA is properly evaluated
        $this->app['auth']->forgetGuards();

        // User A (Owner) can stream it
        $resAllowed = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->get("/api/v1/documents/store/{$doc->id}");

        $resAllowed->assertStatus(200);
    }

    public function test_courier_single_active_assignment_partial_unique_index(): void
    {
        $courier = CourierProfile::create([
            'user_id' => $this->userA->id,
            'vehicle_type' => 'motor',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $store1 = Store::create([
            'owner_user_id' => $this->userB->id,
            'name' => 'Store 1',
            'address_text' => 'Addr 1',
            'service_radius_km' => 5.0,
        ]);

        $store2 = Store::create([
            'owner_user_id' => $this->userB->id,
            'name' => 'Store 2',
            'address_text' => 'Addr 2',
            'service_radius_km' => 5.0,
        ]);

        // Assignment 1: active
        CourierStoreAssignment::create([
            'courier_id' => $courier->id,
            'store_id' => $store1->id,
            'recruited_by' => $this->userB->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Assignment 2: attempting another active assignment for same courier must throw PDOException due to partial unique index
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CourierStoreAssignment::create([
            'courier_id' => $courier->id,
            'store_id' => $store2->id,
            'recruited_by' => $this->userB->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function test_private_files_are_not_accessible_via_public_url(): void
    {
        // Place a simulated KTP file in private storage
        $filename = 'ktp_test_unexposed.jpg';
        $privatePath = 'private/ktp/' . $filename;
        Storage::disk('local')->put($privatePath, 'SENSITIVE_KTP_CONTENT');

        // Public URL route (/storage/...) blocks or cannot find private files (403 or 404)
        $publicUrlResponse = $this->get('/storage/' . $privatePath);
        $this->assertTrue(in_array($publicUrlResponse->status(), [403, 404]), 'Private file must not be accessible via public URL.');
    }
}
