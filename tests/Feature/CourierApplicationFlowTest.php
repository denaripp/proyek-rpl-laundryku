<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $storeOwner;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $customerRole = Role::where('name', 'customer')->first();
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Calon Kurir Test',
            'phone' => '081299990001',
        ]);
        if ($customerRole) {
            $this->customer->roles()->attach($customerRole->id);
        }

        $storeAdminRole = Role::where('name', 'store_admin')->first();
        $this->storeOwner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Owner Toko Test',
        ]);
        if ($storeAdminRole) {
            $this->storeOwner->roles()->attach($storeAdminRole->id);
        }

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundryku Outlet Test',
            'is_active' => true,
            'status' => 'approved',
        ]);
    }

    /**
     * Test 1: Pengajuan kurir menjadi pending, muncul di tab Pelamar Toko, dan tidak langsung aktif.
     */
    public function test_pengajuan_kurir_menjadi_pending_muncul_di_tab_pelamar_toko_dan_tidak_aktif(): void
    {
        Sanctum::actingAs($this->customer);

        $payload = [
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 TEST',
            'nik' => '3201123456780001',
        ];

        // 1. Customer mengirim pengajuan kurir
        $applyRes = $this->postJson('/api/v1/upgrades/courier', $payload);
        $applyRes->assertStatus(201);
        $applyRes->assertJsonPath('data.application.status', 'pending');
        $applyRes->assertJsonPath('data.application.verification_status', 'pending');

        // Pastikan di database tidak langsung aktif
        $this->assertDatabaseHas('couriers', [
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'is_verified' => false,
            'is_available' => false,
        ]);

        // 2. Di sisi customer: status adalah pending ("Menunggu review")
        $statusRes = $this->getJson('/api/v1/upgrades/courier');
        $statusRes->assertStatus(200);
        $statusRes->assertJsonPath('data.application.status', 'pending');
        $statusRes->assertJsonPath('data.application.verification_status', 'pending');
        $statusRes->assertJsonPath('data.application.store_name', $this->store->name);

        // 3. Di sisi toko: masuk ke tab Pelamar Kurir Toko Ini (available_candidates) dan BUKAN kurir aktif (assigned_couriers)
        Sanctum::actingAs($this->storeOwner, ['role:store_admin']);

        $couriersRes = $this->getJson('/api/v1/store-admin/couriers');
        $couriersRes->assertStatus(200);

        // assigned_couriers harus 0 (tidak boleh aktif di toko sebelum disetujui)
        $this->assertCount(0, $couriersRes->json('data.assigned_couriers'));

        // available_candidates harus ada 1 pelamar
        $candidates = $couriersRes->json('data.available_candidates');
        $this->assertCount(1, $candidates);
        $this->assertEquals($this->customer->name, $candidates[0]['name']);
        $this->assertEquals('pending', $candidates[0]['verification_status']);

        // Sidebar count pelamar_kurir_pending juga harus 1
        $sidebarRes = $this->getJson('/api/v1/store/sidebar-counts');
        $sidebarRes->assertStatus(200);
        $sidebarRes->assertJsonPath('data.pelamar_kurir_pending', 1);
    }

    /**
     * Test 2: Pengajuan kurir yang disetujui toko menjadi aktif dan terikat ke toko.
     */
    public function test_pengajuan_disetujui_menjadi_aktif_dan_terikat_ke_toko(): void
    {
        // Setup calon kurir yang sudah mengajukan lamaran (pending)
        $courier = Courier::create([
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 TEST',
            'is_verified' => false,
            'is_available' => false,
        ]);

        Sanctum::actingAs($this->storeOwner, ['role:store_admin']);

        // Toko menyetujui pengajuan kurir
        $recruitRes = $this->postJson("/api/v1/store-admin/couriers/recruit/{$courier->id}");
        $recruitRes->assertStatus(200);
        $recruitRes->assertJsonPath('success', true);

        // Database: terikat aktif
        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'store_id' => $this->store->id,
            'is_verified' => true,
            'is_available' => true,
        ]);

        // Cek dashboard toko: sekarang muncul di assigned_couriers
        $couriersRes = $this->getJson('/api/v1/store-admin/couriers');
        $couriersRes->assertStatus(200);
        $this->assertCount(1, $couriersRes->json('data.assigned_couriers'));
        $this->assertEquals('active', $couriersRes->json('data.assigned_couriers.0.status'));
        $this->assertCount(0, $couriersRes->json('data.available_candidates'));

        // Cek sisi customer: status berubah menjadi approved
        Sanctum::actingAs($this->customer);
        $statusRes = $this->getJson('/api/v1/upgrades/courier');
        $statusRes->assertStatus(200);
        $statusRes->assertJsonPath('data.application.status', 'approved');
        $statusRes->assertJsonPath('data.application.verification_status', 'approved');
    }

    /**
     * Test 3: Pengajuan kurir yang ditolak toko tidak terikat dan status di pelanggan menjadi ditolak.
     */
    public function test_pengajuan_ditolak_tidak_terikat_dan_status_pelanggan_ditolak(): void
    {
        // Setup calon kurir yang mengajukan lamaran (pending)
        $courier = Courier::create([
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 TEST',
            'is_verified' => false,
            'is_available' => false,
        ]);

        Sanctum::actingAs($this->storeOwner, ['role:store_admin']);

        // Toko menolak pengajuan kurir
        $rejectRes = $this->postJson("/api/v1/store-admin/couriers/reject/{$courier->id}", [
            'reason' => 'Kapasitas armada toko saat ini sudah penuh.',
        ]);
        $rejectRes->assertStatus(200);
        $rejectRes->assertJsonPath('success', true);

        // Database: keterikatan diputus (store_id null, tidak terikat)
        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'store_id' => null,
            'is_verified' => false,
            'is_available' => false,
        ]);

        // Dashboard toko: tidak ada di assigned_couriers maupun available_candidates
        $couriersRes = $this->getJson('/api/v1/store-admin/couriers');
        $couriersRes->assertStatus(200);
        $this->assertCount(0, $couriersRes->json('data.assigned_couriers'));
        $this->assertCount(0, $couriersRes->json('data.available_candidates'));

        // Cek sisi customer: status menjadi rejected dengan catatan alasan penolakan
        Sanctum::actingAs($this->customer);
        $statusRes = $this->getJson('/api/v1/upgrades/courier');
        $statusRes->assertStatus(200);
        $statusRes->assertJsonPath('data.application.status', 'rejected');
        $statusRes->assertJsonPath('data.application.verification_status', 'rejected');
        $statusRes->assertJsonPath('data.application.rejection_reason', 'Kapasitas armada toko saat ini sudah penuh.');
        $statusRes->assertJsonPath('data.application.store_name', $this->store->name);
    }
}
