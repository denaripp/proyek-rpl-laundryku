<?php

namespace Tests\Feature;

use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreSidebarCountsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $otherOwner;
    protected Store $store;
    protected Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $storeAdminRole = Role::where('code', 'store_admin')->first();

        $this->owner = User::create([
            'name' => 'Owner Satu',
            'email' => 'owner1@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->owner->id]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'active',
        ]);

        $this->store = Store::create([
            'owner_user_id' => $this->owner->id,
            'name' => 'Toko Laundry Barokah',
            'address_text' => 'Jl. Dipati Ukur No. 1, Bandung',
            'latitude' => -6.892,
            'longitude' => 107.616,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        // Other store
        $this->otherOwner = User::create([
            'name' => 'Owner Dua',
            'email' => 'owner2@example.com',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->otherOwner->id]);
        UserRole::create([
            'user_id' => $this->otherOwner->id,
            'role_id' => $storeAdminRole->id,
            'status' => 'active',
        ]);

        $this->otherStore = Store::create([
            'owner_user_id' => $this->otherOwner->id,
            'name' => 'Toko Laundry Lain',
            'address_text' => 'Jl. Dago No. 2, Bandung',
            'latitude' => -6.885,
            'longitude' => 107.615,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);
    }

    /**
     * Test 1: Hitungan pelamar pending hanya menghitung status pending untuk toko ini.
     * Pelamar approved atau rejected tidak dihitung, dan pelamar toko lain tidak ikut terhitung.
     */
    public function test_sidebar_counts_pending_couriers_properly_scoped(): void
    {
        Sanctum::actingAs($this->owner);

        // 1. Pending candidate for this store -> should be counted (+1)
        $cand1 = User::create(['name' => 'Kandidat 1', 'email' => 'kand1@test.com', 'status' => 'active']);
        CourierProfile::create([
            'user_id' => $cand1->id,
            'store_id' => $this->store->id,
            'identity_encrypted' => '3201010101010001',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_encrypted' => 'D 1234 ABC',
            'verification_status' => 'pending',
            'status' => 'active',
        ]);

        // 2. Approved candidate for this store -> should NOT be counted
        $cand2 = User::create(['name' => 'Kandidat 2', 'email' => 'kand2@test.com', 'status' => 'active']);
        $p2 = CourierProfile::create([
            'user_id' => $cand2->id,
            'store_id' => $this->store->id,
            'identity_encrypted' => '3201010101010002',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_encrypted' => 'D 2345 BCD',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        CourierStoreAssignment::create([
            'courier_id' => $p2->id,
            'store_id' => $this->store->id,
            'recruited_by' => $this->owner->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // 3. Rejected candidate for this store -> should NOT be counted
        $cand3 = User::create(['name' => 'Kandidat 3', 'email' => 'kand3@test.com', 'status' => 'active']);
        CourierProfile::create([
            'user_id' => $cand3->id,
            'store_id' => $this->store->id,
            'identity_encrypted' => '3201010101010003',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_encrypted' => 'D 3456 CDE',
            'verification_status' => 'rejected',
            'status' => 'inactive',
        ]);

        // 4. Pending candidate for OTHER store -> should NOT be counted
        $candOther = User::create(['name' => 'Kandidat Toko Lain', 'email' => 'kandother@test.com', 'status' => 'active']);
        CourierProfile::create([
            'user_id' => $candOther->id,
            'store_id' => $this->otherStore->id,
            'identity_encrypted' => '3201010101010004',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_encrypted' => 'D 4567 DEF',
            'verification_status' => 'pending',
            'status' => 'active',
        ]);

        // 5. Orders: 1 PENDING_PICKUP for this store, 1 for other store
        $customer = User::create(['name' => 'Customer Test', 'email' => 'cus@test.com', 'status' => 'active']);
        $addressSnapshot = ['address_text' => 'Jl. Test No. 1'];
        Order::create([
            'order_number' => 'ORD-101',
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => 'PENDING_PICKUP',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'estimated_weight_kg' => 2.0,
            'pickup_address_snapshot' => $addressSnapshot,
            'delivery_address_snapshot' => $addressSnapshot,
        ]);
        Order::create([
            'order_number' => 'ORD-102',
            'customer_id' => $customer->id,
            'store_id' => $this->otherStore->id,
            'status' => 'PENDING_PICKUP',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'estimated_weight_kg' => 2.0,
            'pickup_address_snapshot' => $addressSnapshot,
            'delivery_address_snapshot' => $addressSnapshot,
        ]);

        $response = $this->getJson("/api/v1/store/sidebar-counts?store_id={$this->store->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pelamar_kurir_pending' => 1,
                    'pesanan_baru' => 1,
                    'store_id' => $this->store->id,
                ],
            ]);
    }
}
