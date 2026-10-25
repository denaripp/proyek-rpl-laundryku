<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraRegisterDirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * 1. Register valid oleh customer terautentikasi:
     * - user created
     * - toko berstatus submitted
     * - pivot role terisi dengan store_id
     * - audit log STORE_REGISTER tercatat
     * - notifikasi terbuat untuk user dan admin
     * - sesi customer tetap aktif
     */
    public function test_valid_mitra_registration_creates_user_store_pivot_audit_and_notifications(): void
    {
        // Setup platform admin to receive notification
        $admin = User::factory()->create([
            'email' => 'admin_direct@laundryku.test',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $adminRole = Role::whereIn('name', ['platform_owner', 'platform_admin', 'super_admin'])->first();
        if ($adminRole) {
            $admin->roles()->attach($adminRole->id);
        }

        // Pendaftar login sebagai customer
        $customer = User::factory()->create([
            'email' => 'customer_applicant_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'role' => User::ROLE_CUSTOMER,
        ]);
        Sanctum::actingAs($customer, ['role:customer']);

        $uniqueEmail = 'budimitra_' . uniqid() . '@example.com';
        $uniquePhone = '0888' . rand(10000000, 99999999);

        $payload = [
            'name' => 'Budi Mitra Baru',
            'email' => $uniqueEmail,
            'phone' => $uniquePhone,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Berkah Kilat',
            'store_address' => 'Jl. Dipati Ukur No. 45, Bandung',
            'latitude' => -6.89012345,
            'longitude' => 107.61512345,
        ];

        $response = $this->postJson('/api/v1/auth/mitra/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('store.status', 'submitted');
        $response->assertJsonStructure([
            'message',
            'token',
            'token_type',
            'user' => ['id', 'name', 'email', 'role'],
            'store' => ['id', 'name', 'status', 'is_active'],
        ]);

        // Verifikasi User mitra dibuat terpisah
        $mitraUser = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($mitraUser);
        $this->assertNotEquals($customer->id, $mitraUser->id);
        $this->assertSame(User::ROLE_STORE_ADMIN, $mitraUser->role);

        // Verifikasi Store dibuat dengan status submitted
        $store = Store::where('owner_user_id', $mitraUser->id)->first();
        $this->assertNotNull($store);
        $this->assertSame('Laundry Berkah Kilat', $store->name);
        $this->assertSame('submitted', $store->status);
        $this->assertEquals(-6.89012345, (float) $store->latitude);
        $this->assertEquals(107.61512345, (float) $store->longitude);

        // Verifikasi pivot role user_roles terisi dengan store_id
        $mitraRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first();
        $this->assertNotNull($mitraRole);
        $pivot = DB::table('user_roles')
            ->where('user_id', $mitraUser->id)
            ->where('role_id', $mitraRole->id)
            ->where('store_id', $store->id)
            ->first();
        $this->assertNotNull($pivot, 'Pivot user_roles must contain store_id.');

        // Verifikasi audit log tercatat
        $audit = AuditLog::where('action', 'STORE_REGISTER')
            ->where('actor_id', $mitraUser->id)
            ->first();
        $this->assertNotNull($audit, 'Audit log STORE_REGISTER must be created.');

        // Verifikasi notifikasi terbuat untuk user pemohon
        $userNotif = Notification::where('user_id', $mitraUser->id)->first();
        $this->assertNotNull($userNotif, 'Applicant notification must exist.');

        // Verifikasi notifikasi terbuat untuk admin antrean verifikasi
        $adminNotif = Notification::where('user_id', $admin->id)->first();
        $this->assertNotNull($adminNotif, 'Admin verification queue notification must exist.');

        // Sesi customer tetap aktif
        $meRes = $this->getJson('/api/v1/me');
        $meRes->assertStatus(200);
        $meRes->assertJsonPath('data.id', (string) $customer->id);
    }

    /**
     * 2. Register tanpa login ditolak 401
     */
    public function test_register_without_login_is_rejected_401(): void
    {
        $payload = [
            'name' => 'Guest Mitra',
            'email' => 'guest_mitra_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Guest',
            'store_address' => 'Jl. Guest No. 1',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ];

        $response = $this->postJson('/api/v1/auth/mitra/register', $payload);
        $response->assertStatus(401);
    }

    /**
     * 3. Register oleh non-customer ditolak 403
     */
    public function test_register_by_non_customer_is_rejected_403(): void
    {
        // Login sebagai courier
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
        ]);
        Sanctum::actingAs($courier, ['role:courier']);

        $payload = [
            'name' => 'Kurir Jadi Mitra',
            'email' => 'kurir_mitra_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Kurir',
            'store_address' => 'Jl. Kurir No. 1',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ];

        $response = $this->postJson('/api/v1/auth/mitra/register', $payload);
        $response->assertStatus(403);
    }

    /**
     * 4. Validasi gagal: koordinat di luar rentang, nomor telepon tidak valid.
     */
    public function test_mitra_registration_validation_fails_on_invalid_coordinates_and_phone(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer, ['role:customer']);

        $payloadInvalidCoords = [
            'name' => 'Calon Mitra',
            'email' => 'calonmitra_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Angin',
            'store_address' => 'Jl. Merdeka No. 1',
            'latitude' => 150.0, // di luar batas -90..90
            'longitude' => 200.0, // di luar batas -180..180
        ];

        $responseCoords = $this->postJson('/api/v1/auth/mitra/register', $payloadInvalidCoords);
        $responseCoords->assertStatus(422);
        $responseCoords->assertJsonValidationErrors(['latitude', 'longitude']);

        $payloadInvalidPhone = [
            'name' => 'Calon Mitra 2',
            'email' => 'calonmitra2_' . uniqid() . '@example.com',
            'phone' => 'abc-123-bukan-hp',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Angin 2',
            'store_address' => 'Jl. Merdeka No. 2',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ];

        $responsePhone = $this->postJson('/api/v1/auth/mitra/register', $payloadInvalidPhone);
        $responsePhone->assertStatus(422);
        $responsePhone->assertJsonValidationErrors(['phone']);
    }

    /**
     * 5. Penolakan koordinat default (0, 0) atau koordinat kosong
     */
    public function test_mitra_registration_rejected_when_coordinates_are_zero_zero_or_empty(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer, ['role:customer']);

        $payloadZeroCoords = [
            'name' => 'Calon Mitra Nol',
            'email' => 'calonmitranol_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Nol Island',
            'store_address' => 'Jl. Khatulistiwa No. 0',
            'latitude' => 0.0,
            'longitude' => 0.0,
        ];

        $resZero = $this->postJson('/api/v1/auth/mitra/register', $payloadZeroCoords);
        $resZero->assertStatus(422);
        $resZero->assertJsonValidationErrors(['latitude']);
        $this->assertStringContainsString('0, 0', $resZero->json('errors.latitude.0'));

        $payloadEmptyCoords = [
            'name' => 'Calon Mitra Kosong',
            'email' => 'calonmitrakosong_' . uniqid() . '@example.com',
            'phone' => '0877' . rand(10000000, 99999999),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Tanpa Koordinat',
            'store_address' => 'Jl. Antah Berantah No. 99',
            'latitude' => null,
            'longitude' => null,
        ];

        $resEmpty = $this->postJson('/api/v1/auth/mitra/register', $payloadEmptyCoords);
        $resEmpty->assertStatus(422);
        $resEmpty->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * 6. Register dengan email atau nomor telepon sama dengan customer yang sedang login ditolak 422
     */
    public function test_mitra_registration_rejected_when_email_or_phone_belongs_to_logged_in_customer(): void
    {
        $custEmail = 'customer_saya_' . uniqid() . '@example.com';
        $custPhone = '08129999' . rand(1000, 9999);

        $customer = User::factory()->create([
            'email' => $custEmail,
            'phone' => $custPhone,
            'role' => User::ROLE_CUSTOMER,
        ]);
        Sanctum::actingAs($customer, ['role:customer']);

        // Coba daftar mitra dengan email yang sama dengan customer yang login
        $responseSameEmail = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Mitra Baru',
            'email' => $custEmail,
            'phone' => '088811112222',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Beda',
            'store_address' => 'Jl. Riau No. 10',
            'latitude' => -6.91,
            'longitude' => 107.62,
        ]);

        $responseSameEmail->assertStatus(422);
        $responseSameEmail->assertJsonFragment([
            'message' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
        ]);

        // Coba daftar mitra dengan nomor telepon yang sama dengan customer yang login
        $responseSamePhone = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Mitra Baru',
            'email' => 'email_lain_' . uniqid() . '@example.com',
            'phone' => $custPhone,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Beda 2',
            'store_address' => 'Jl. Riau No. 11',
            'latitude' => -6.91,
            'longitude' => 107.62,
        ]);

        $responseSamePhone->assertStatus(422);
        $responseSamePhone->assertJsonFragment([
            'message' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
        ]);
    }

    /**
     * 7. Varian huruf besar dan spasi pada email serta varian format telepon ditolak 422
     */
    public function test_mitra_registration_normalizes_and_rejects_uppercase_spaces_and_phone_variants(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer, ['role:customer']);

        // Buat user yang sudah ada di database
        $existingEmail = 'pemilik_toko@example.com';
        $existingPhone = '081234567890';
        User::factory()->create([
            'email' => $existingEmail,
            'phone' => $existingPhone,
        ]);

        // Kirim email dengan huruf besar dan spasi di awal/akhir
        $resVariantEmail = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Mitra Varian',
            'email' => '   PeMiLiK_ToKo@ExAmPlE.CoM   ',
            'phone' => '087799887766',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Varian 1',
            'store_address' => 'Jl. Varian No. 1',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ]);
        $resVariantEmail->assertStatus(422);
        $resVariantEmail->assertJsonFragment([
            'message' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
        ]);

        // Kirim nomor telepon dengan format +62 dan strip/spasi yang setara dengan 081234567890
        $resVariantPhone = $this->postJson('/api/v1/auth/mitra/register', [
            'name' => 'Mitra Varian Phone',
            'email' => 'varian_email_' . uniqid() . '@example.com',
            'phone' => '+62 812-3456-7890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Laundry Varian 2',
            'store_address' => 'Jl. Varian No. 2',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ]);
        $resVariantPhone->assertStatus(422);
        $resVariantPhone->assertJsonFragment([
            'message' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
        ]);
    }

    /**
     * 8. /me hanya mengembalikan toko milik sendiri
     */
    public function test_me_endpoint_returns_only_owned_stores(): void
    {
        $userA = User::factory()->create([
            'email' => 'user_a_' . uniqid() . '@example.com',
            'role' => User::ROLE_STORE_ADMIN,
        ]);
        $storeA = Store::factory()->create([
            'owner_user_id' => $userA->id,
            'name' => 'Toko Laundry A',
            'status' => 'submitted',
            'address' => 'Alamat Toko A',
        ]);

        $userB = User::factory()->create([
            'email' => 'user_b_' . uniqid() . '@example.com',
            'role' => User::ROLE_STORE_ADMIN,
        ]);
        $storeB = Store::factory()->create([
            'owner_user_id' => $userB->id,
            'name' => 'Toko Laundry B',
            'status' => 'approved',
            'address' => 'Alamat Toko B',
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $stores = $response->json('data.stores');
        $this->assertCount(1, $stores);
        $this->assertSame((string) $storeA->id, $stores[0]['id']);
        $this->assertSame('Toko Laundry A', $stores[0]['name']);
        $this->assertSame('submitted', $stores[0]['status']);
        $this->assertSame('Alamat Toko A', $stores[0]['address']);
        $this->assertNull($stores[0]['rejection_reason']);
    }

    /**
     * 9. Status di /me berubah setelah admin approve dan setelah reject (beserta alasan)
     */
    public function test_me_reflects_status_changes_after_admin_approve_and_reject(): void
    {
        $user = User::factory()->create([
            'email' => 'mitra_status_track_' . uniqid() . '@example.com',
            'role' => User::ROLE_STORE_ADMIN,
        ]);

        $store = Store::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Toko Status Tracker',
            'status' => 'submitted',
            'address' => 'Jl. Pelacak No. 123',
        ]);

        Sanctum::actingAs($user);

        // Status awal: submitted
        $resInitial = $this->getJson('/api/v1/me');
        $resInitial->assertStatus(200);
        $resInitial->assertJsonPath('data.stores.0.status', 'submitted');
        $resInitial->assertJsonPath('data.stores.0.rejection_reason', null);

        // Kasus 1: Admin approve
        $store->status = 'approved';
        $store->approved_at = now();
        $store->save();

        $resApproved = $this->getJson('/api/v1/me');
        $resApproved->assertStatus(200);
        $resApproved->assertJsonPath('data.stores.0.status', 'approved');
        $resApproved->assertJsonPath('data.stores.0.rejection_reason', null);

        // Kasus 2: Toko ditolak dengan alasan penolakan
        $store->status = 'rejected';
        $store->save();

        StoreApplication::updateOrCreate(
            ['store_id' => $store->id],
            [
                'rejected_at' => now(),
                'rejection_reason' => 'Foto dokumen SIUP tidak terbaca dan buram.',
            ]
        );

        $resRejected = $this->getJson('/api/v1/me');
        $resRejected->assertStatus(200);
        $resRejected->assertJsonPath('data.stores.0.status', 'rejected');
        $resRejected->assertJsonPath('data.stores.0.rejection_reason', 'Foto dokumen SIUP tidak terbaca dan buram.');
    }
}
