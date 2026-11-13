<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierPortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeOwner;
    protected Store $store;
    protected User $courierUser;
    protected Courier $courier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $storeAdminRole = Role::where('name', 'store_admin')->first();
        $this->storeOwner = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'name' => 'Pemilik Toko Laundry',
        ]);
        if ($storeAdminRole) {
            $this->storeOwner->roles()->attach($storeAdminRole->id);
        }

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Laundryku Outlet Pusat',
            'is_active' => true,
            'status' => 'approved',
        ]);

        $courierRole = Role::where('name', 'courier')->first();
        $this->courierUser = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'name' => 'Arieef Kurir Tangguh',
            'email' => 'arieef.courier@laundryku.test',
        ]);
        if ($courierRole) {
            $this->courierUser->roles()->attach($courierRole->id);
        }

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'E 5678 ARF',
            'is_verified' => true,
            'is_available' => true,
        ]);
    }

    /**
     * Test: Kurir terverifikasi bisa memanggil endpoint tugas (200).
     */
    public function test_kurir_terverifikasi_bisa_memanggil_endpoint_tugas(): void
    {
        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->getJson('/api/v1/courier/tasks?tab=available');
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    /**
     * Test: Kurir yang belum disetujui / belum diverifikasi ditolak (403).
     */
    public function test_kurir_belum_disetujui_ditolak_mengakses_tugas_dan_profil(): void
    {
        // Kurir belum diverifikasi
        $this->courier->update(['is_verified' => false]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $taskRes = $this->getJson('/api/v1/courier/tasks?tab=available');
        $taskRes->assertStatus(403);
        $taskRes->assertJsonPath('success', false);

        $profileRes = $this->getJson('/api/v1/courier/profile');
        $profileRes->assertStatus(403);
        $profileRes->assertJsonPath('success', false);
    }

    /**
     * Test: Profil kurir mengembalikan nama yang benar dari akun login.
     */
    public function test_profil_kurir_mengembalikan_nama_yang_benar(): void
    {
        Sanctum::actingAs($this->courierUser, ['role:courier']);

        $res = $this->getJson('/api/v1/courier/profile');
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.name', 'Arieef Kurir Tangguh');
        $res->assertJsonPath('data.email', 'arieef.courier@laundryku.test');
        $res->assertJsonPath('data.verification_status', 'verified');
    }

    /**
     * Test: User dengan token customer ditolak mengakses portal kurir (harus token role:courier).
     */
    public function test_token_customer_lama_dapat_mengakses_portal_kurir_setelah_role_kurir_aktif(): void
    {
        // Token customer harus ditolak 403 karena tidak memiliki ability role:courier
        Sanctum::actingAs($this->courierUser, ['role:customer']);

        $res = $this->getJson('/api/v1/courier/tasks?tab=available');
        $res->assertStatus(403);

        $profileRes = $this->getJson('/api/v1/courier/profile');
        $profileRes->assertStatus(403);

        // Setelah login dengan token role:courier, baru diizinkan (200)
        Sanctum::actingAs($this->courierUser, ['role:courier']);
        $validRes = $this->getJson('/api/v1/courier/profile');
        $validRes->assertStatus(200);
        $validRes->assertJsonPath('data.name', 'Arieef Kurir Tangguh');
    }

    /**
     * Test: Kurir pemegang tugas bisa update status order menggunakan order_id (200).
     */
    public function test_kurir_pemegang_tugas_bisa_update_status_order(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);

        $task = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        // 1. Update status menggunakan Order ID (dengan to_status uppercase PICKED_UP) -> 200
        $resPickedUp = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'PICKED_UP',
            'note' => 'Cucian berhasil dijemput dari pelanggan',
        ]);

        $resPickedUp->assertStatus(200);
        $resPickedUp->assertJsonPath('success', true);
        $this->assertEquals('picked_up', $order->fresh()->status);

        // 2. Update status berikutnya menggunakan Order ID (at_store) -> 200
        $resAtStore = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'at_store',
            'note' => 'Cucian sudah sampai di toko',
        ]);

        $resAtStore->assertStatus(200);
        $resAtStore->assertJsonPath('success', true);
        $this->assertEquals('at_store', $order->fresh()->status);
    }

    /**
     * Test bentrok ID: Kurir A punya task ID X untuk Order Y dan juga Order ID X.
     * POST ke /courier/tasks/X harus mengubah Order X, bukan Order Y. Kurir B ditolak.
     */
    public function test_bentrok_id_task_dan_order_mengubah_order_x_bukan_order_y_dan_kurir_b_ditolak(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        // Buat Order X dengan ID 100 untuk Kurir A
        $orderX = Order::factory()->create([
            'id' => 100,
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);

        // Buat Order Y dengan ID 200 untuk Kurir A
        $orderY = Order::factory()->create([
            'id' => 200,
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);

        // Buat CourierTask untuk Order Y dengan ID 100 (bentrok dengan ID Order X)
        $taskY = CourierTask::create([
            'id' => 100,
            'order_id' => $orderY->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        // Buat Kurir B
        $courierRole = Role::where('name', 'courier')->first();
        $courierUserB = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'name' => 'Kurir B',
        ]);
        if ($courierRole) {
            $courierUserB->roles()->attach($courierRole->id);
        }

        Courier::create([
            'user_id' => $courierUserB->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'D 9999 XYZ',
            'is_verified' => true,
            'is_available' => true,
        ]);

        // 1. Kurir A melakukan transition ke /courier/tasks/100
        Sanctum::actingAs($this->courierUser, ['role:courier']);
        $resA = $this->postJson("/api/v1/courier/tasks/{$orderX->id}/transition", [
            'to_status' => 'picked_up',
            'note' => 'Jemput order X',
        ]);

        $resA->assertStatus(200);
        $resA->assertJsonPath('success', true);

        // Pastikan Order X yang terupdate ke picked_up
        $this->assertEquals('picked_up', $orderX->fresh()->status);

        // Pastikan Order Y TIDAK berubah (tetap pickup_assigned)
        $this->assertEquals('pickup_assigned', $orderY->fresh()->status);

        // 2. Kurir B mencoba mengakses /courier/tasks/100 (Order X milik Kurir A) -> DITOLAK 403
        Sanctum::actingAs($courierUserB, ['role:courier']);
        $resB = $this->postJson("/api/v1/courier/tasks/{$orderX->id}/transition", [
            'to_status' => 'at_store',
        ]);

        $resB->assertStatus(403);
        $resB->assertJsonPath('success', false);
        $resB->assertJsonPath('message', 'Anda tidak memiliki hak akses untuk mengubah tugas pesanan ini.');
    }

    /**
     * Test: Kurir lain ditolak saat mencoba update status tugas kurir pemegang tugas (403).
     */
    public function test_kurir_lain_ditolak_mengubah_status_tugas(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);

        $task = CourierTask::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'accepted',
        ]);

        // Buat kurir kedua
        $courierRole = Role::where('name', 'courier')->first();
        $otherCourierUser = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'name' => 'Kurir Lain',
        ]);
        if ($courierRole) {
            $otherCourierUser->roles()->attach($courierRole->id);
        }

        Courier::create([
            'user_id' => $otherCourierUser->id,
            'store_id' => $this->store->id,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B 1234 XYZ',
            'is_verified' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($otherCourierUser, ['role:courier']);

        // Percobaan menggunakan Order ID oleh kurir lain -> 403
        $resOrder = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'picked_up',
        ]);
        $resOrder->assertStatus(403);
        $resOrder->assertJsonPath('success', false);
    }

    /**
     * Test: Transisi status tidak valid ditolak (422).
     */
    public function test_transisi_status_tidak_valid_ditolak(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        // Lompat status dari pickup_assigned ke completed
        $res = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'completed',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('success', false);
    }

    /**
     * Test: Profil kurir mengembalikan statistik tugas aktif dan riwayat selesai yang konsisten.
     */
    public function test_profil_kurir_mengembalikan_statistik_tugas_konsisten_dengan_order(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        // Buat 1 order aktif ditugaskan ke kurir
        $orderActive = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'pickup_assigned',
        ]);
        CourierTask::create([
            'order_id' => $orderActive->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'pickup',
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        // Buat 1 order selesai
        $orderCompleted = Order::factory()->create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'courier_id' => $this->courierUser->id,
            'status' => 'completed',
        ]);
        CourierTask::create([
            'order_id' => $orderCompleted->id,
            'courier_id' => $this->courier->id,
            'task_type' => 'delivery',
            'status' => 'delivered',
            'assigned_at' => now()->subDay(),
            'delivered_at' => now()->subHours(2),
        ]);

        Sanctum::actingAs($this->courierUser, ['role:courier']);

        // Check profile stats
        $resProfile = $this->getJson('/api/v1/courier/profile');
        $resProfile->assertStatus(200);
        $resProfile->assertJsonPath('data.stats.active_tasks', 1);
        $resProfile->assertJsonPath('data.stats.completed_tasks', 1);

        // Check active tasks
        $resActive = $this->getJson('/api/v1/courier/tasks?tab=active');
        $resActive->assertStatus(200);
        $resActive->assertJsonCount(1, 'data');

        // Check completed tasks
        $resCompleted = $this->getJson('/api/v1/courier/tasks?tab=completed');
        $resCompleted->assertStatus(200);
        $resCompleted->assertJsonCount(1, 'data');
    }
}
