<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraCourierPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $mitraUserA;
    protected Store $storeA;
    protected User $mitraUserB;
    protected Store $storeB;
    protected User $courierUserA;
    protected Courier $courierA;
    protected User $courierUserB;
    protected Courier $courierB;
    protected User $customer;
    protected Service $serviceWash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Mitra A & Store A
        $this->mitraUserA = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);
        $storeAdminRole = Role::where('name', 'store_admin')->first();
        if ($storeAdminRole) {
            $this->mitraUserA->roles()->attach($storeAdminRole->id);
        }

        $this->storeA = Store::factory()->create([
            'owner_user_id' => $this->mitraUserA->id,
            'name' => 'Laundry Berkah A',
            'is_active' => true,
            'status' => 'approved',
        ]);

        // 2. Mitra B & Store B
        $this->mitraUserB = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);
        if ($storeAdminRole) {
            $this->mitraUserB->roles()->attach($storeAdminRole->id);
        }

        $this->storeB = Store::factory()->create([
            'owner_user_id' => $this->mitraUserB->id,
            'name' => 'Laundry Sukses B',
            'is_active' => true,
            'status' => 'approved',
        ]);

        // 3. Courier A (Assigned to Store A)
        $this->courierUserA = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);
        $courierRole = Role::where('name', 'courier')->first();
        if ($courierRole) {
            $this->courierUserA->roles()->attach($courierRole->id);
        }

        $this->courierA = Courier::factory()->create([
            'user_id' => $this->courierUserA->id,
            'store_id' => $this->storeA->id,
            'is_verified' => true,
            'is_available' => true,
        ]);

        // 4. Courier B (Assigned to Store B)
        $this->courierUserB = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);
        if ($courierRole) {
            $this->courierUserB->roles()->attach($courierRole->id);
        }

        $this->courierB = Courier::factory()->create([
            'user_id' => $this->courierUserB->id,
            'store_id' => $this->storeB->id,
            'is_verified' => true,
            'is_available' => true,
        ]);

        // 5. Customer
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $this->customer->roles()->attach($customerRole->id);
        }

        // 6. Global Service
        $this->serviceWash = Service::firstOrCreate(
            ['name' => 'Cuci Kering Setrika'],
            ['description' => 'Layanan cuci lipat setrika wangi', 'is_active' => true]
        );
    }

    public function test_unauthenticated_cannot_access_mitra_or_courier_portals(): void
    {
        $this->getJson('/api/v1/store/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/store/services')->assertStatus(401);
        $this->getJson('/api/v1/store-admin/couriers')->assertStatus(401);
        $this->getJson('/api/v1/courier/profile')->assertStatus(401);
        $this->getJson('/api/v1/courier/tasks')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_customer_cannot_access_mitra_or_courier_portals(): void
    {
        Sanctum::actingAs($this->customer, ['role:customer']);

        $this->getJson('/api/v1/store/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/store/services')->assertStatus(403);
        $this->getJson('/api/v1/store-admin/couriers')->assertStatus(403);
        $this->getJson('/api/v1/courier/profile')->assertStatus(403);
        $this->getJson('/api/v1/courier/tasks')->assertStatus(403);
    }

    public function test_mitra_can_access_dashboard_with_canonical_envelope(): void
    {
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // Create 1 order for Store A
        Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_pickup',
            'total_amount' => 50000,
        ]);

        $res = $this->getJson('/api/v1/store/dashboard');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store.id', (string) $this->storeA->id)
            ->assertJsonPath('data.store.name', 'Laundry Berkah A')
            ->assertJsonPath('data.metrics.new_orders', 1);

        $data = $res->json('data');
        $this->assertArrayHasKey('store', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('recent_orders', $data);
    }

    public function test_courier_can_access_profile_and_tasks_with_canonical_envelope(): void
    {
        Sanctum::actingAs($this->courierUserA, ['role:courier']);

        $resProfile = $this->getJson('/api/v1/courier/profile');

        $resProfile->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_assigned', true)
            ->assertJsonPath('data.courier_profile_id', (string) $this->courierA->id)
            ->assertJsonPath('data.store.id', (string) $this->storeA->id);

        $resTasks = $this->getJson('/api/v1/courier/tasks?tab=all');
        $resTasks->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($resTasks->json('data'));
    }

    public function test_mitra_data_isolation_between_stores(): void
    {
        // Setup ServicePrice for Store A and Store B
        $spA = ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $this->serviceWash->id,
            'price_per_unit' => 10000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $serviceDry = Service::firstOrCreate(
            ['name' => 'Dry Clean Jas'],
            ['description' => 'Khusus pakaian formal', 'is_active' => true]
        );

        $spB = ServicePrice::create([
            'store_id' => $this->storeB->id,
            'service_id' => $serviceDry->id,
            'price_per_unit' => 35000,
            'unit' => 'item',
            'is_active' => true,
        ]);

        // Login as Mitra A
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // Mitra A lists services -> only spA
        $resServices = $this->getJson('/api/v1/store/services');
        $resServices->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $spA->id)
            ->assertJsonPath('data.0.name', 'Cuci Kering Setrika');

        // Mitra A attempts to update or delete spB of Store B -> 404 (not found in Store A)
        $this->patchJson("/api/v1/store/services/{$spB->id}", ['price_per_unit' => 12000])
            ->assertStatus(404);
        $this->deleteJson("/api/v1/store/services/{$spB->id}")
            ->assertStatus(404);

        // Orders isolation on dashboard
        Order::factory()->create([
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_pickup',
            'total_amount' => 99000,
        ]);

        $resDashA = $this->getJson('/api/v1/store/dashboard');
        $resDashA->assertStatus(200)
            ->assertJsonPath('data.metrics.new_orders', 0); // Order belongs to B, so A is 0
    }

    public function test_courier_data_isolation_between_stores_and_couriers(): void
    {
        // Unassigned order in Store B
        Order::factory()->create([
            'store_id' => $this->storeB->id,
            'customer_id' => $this->customer->id,
            'courier_id' => null,
            'status' => 'pending_pickup',
        ]);

        // Unassigned order in Store A
        $orderA = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'courier_id' => null,
            'status' => 'pending_pickup',
        ]);

        // Acting as Courier A (belongs to Store A)
        Sanctum::actingAs($this->courierUserA, ['role:courier']);

        $resAvailable = $this->getJson('/api/v1/courier/tasks?tab=available');
        $resAvailable->assertStatus(200);

        $orderIds = collect($resAvailable->json('data'))->pluck('order_id')->toArray();
        $this->assertContains((string) $orderA->id, $orderIds);
        $this->assertCount(1, $resAvailable->json('data')); // Only orderA from Store A, Store B excluded
    }

    public function test_mutation_mitra_operational_status_writes_audit_log(): void
    {
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        $res = $this->patchJson('/api/v1/store/operational-status', [
            'operational_status' => 'inactive',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operational_status', 'inactive');

        $this->assertDatabaseHas('stores', [
            'id' => $this->storeA->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->mitraUserA->id,
            'action' => 'STORE_OPERATIONAL_STATUS_UPDATE',
            'resource_type' => 'store',
            'resource_id' => $this->storeA->id,
        ]);
    }

    public function test_mutation_mitra_services_crud_writes_audit_log(): void
    {
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        $res = $this->postJson('/api/v1/store/services', [
            'category_id' => $this->serviceWash->id,
            'pricing_unit' => 'kg',
            'price_per_unit' => 12500,
            'is_active' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_per_unit', 12500);

        $spId = $res->json('data.id');

        $this->assertDatabaseHas('service_prices', [
            'id' => $spId,
            'store_id' => $this->storeA->id,
            'price_per_unit' => 12500,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->mitraUserA->id,
            'action' => 'STORE_SERVICE_CREATE',
            'resource_type' => 'service_price',
            'resource_id' => $spId,
        ]);
    }

    public function test_mutation_mitra_recruit_courier_enforces_single_store_and_writes_audit_log(): void
    {
        // Create an unassigned candidate
        $candidateUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        $candidate = Courier::factory()->create([
            'user_id' => $candidateUser->id,
            'store_id' => null,
            'is_verified' => true,
        ]);

        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // Mitra A recruits candidate
        $res = $this->postJson("/api/v1/store-admin/couriers/recruit/{$candidate->id}");
        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('couriers', [
            'id' => $candidate->id,
            'store_id' => $this->storeA->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->mitraUserA->id,
            'action' => 'STORE_COURIER_RECRUIT',
            'resource_type' => 'courier',
            'resource_id' => $candidate->id,
        ]);

        // Mitra B tries to recruit same courier -> rejected with 422
        Sanctum::actingAs($this->mitraUserB, ['role:store_admin']);
        $resFail = $this->postJson("/api/v1/store-admin/couriers/recruit/{$candidate->id}");
        $resFail->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_mutation_mitra_update_complaint_status_writes_audit_log(): void
    {
        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
        ]);

        $dispute = Dispute::create([
            'complaint_number' => 'CMP-TEST-001',
            'order_id' => $order->id,
            'opened_by' => $this->customer->id,
            'reason' => 'DAMAGED_ITEM',
            'description' => 'Baju sobek pada bagian lengan',
            'status' => Dispute::STATUS_COMPLAINT_SUBMITTED,
        ]);

        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        $res = $this->patchJson("/api/v1/store-admin/complaints/{$dispute->id}/status", [
            'status' => 'IN_INVESTIGATION',
            'resolution_note' => 'Sedang diperiksa rekaman CCTV pencucian.',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'IN_INVESTIGATION');

        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => Dispute::STATUS_INVESTIGATION,
            'resolution_note' => 'Sedang diperiksa rekaman CCTV pencucian.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->mitraUserA->id,
            'action' => 'STORE_COMPLAINT_STATUS_UPDATE',
            'resource_type' => 'dispute',
            'resource_id' => $dispute->id,
        ]);
    }

    public function test_mutation_courier_claim_task_and_confirm_cod_writes_audit_log(): void
    {
        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'courier_id' => null,
            'status' => 'pending_pickup',
            'total_amount' => 45000,
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
        ]);

        Sanctum::actingAs($this->courierUserA, ['role:courier']);

        // 1. Claim Task
        $resClaim = $this->postJson("/api/v1/courier/tasks/{$order->id}/claim");
        $resClaim->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.courier_id', (string) $this->courierUserA->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'courier_id' => $this->courierUserA->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->courierUserA->id,
            'action' => 'COURIER_TASK_CLAIM',
            'resource_type' => 'order',
            'resource_id' => $order->id,
        ]);

        // 2. Confirm COD
        $resCod = $this->postJson("/api/v1/courier/tasks/{$order->id}/confirm-cod");
        $resCod->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'PAID');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->courierUserA->id,
            'action' => 'COURIER_CONFIRM_COD',
            'resource_type' => 'order',
            'resource_id' => $order->id,
        ]);
    }

    public function test_notifications_portal_read_and_preferences(): void
    {
        $notif = Notification::create([
            'user_id' => $this->mitraUserA->id,
            'type' => 'order_status_updated',
            'title' => 'Pesanan Baru',
            'content' => 'Ada pesanan baru masuk ke toko Anda.',
            'read_at' => null,
        ]);

        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // List
        $resList = $this->getJson('/api/v1/notifications');
        $resList->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1);

        // Mark Read
        $resRead = $this->patchJson("/api/v1/notifications/{$notif->id}/read");
        $resRead->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'read');

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
        ]);
        $this->assertNotNull($notif->fresh()->read_at);

        // Preferences
        $resPref = $this->getJson('/api/v1/notifications/preferences');
        $resPref->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($resPref->json('data'));
    }

    public function test_delete_service_rejected_when_used_in_order_items(): void
    {
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // 1. ServicePrice yang dipakai di order_items
        $spUsed = ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $this->serviceWash->id,
            'price_per_unit' => 10000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'status' => 'processing',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $this->serviceWash->id,
            'quantity' => 3,
            'unit' => 'kg',
            'price_per_unit' => 10000,
            'total_price' => 30000,
        ]);

        // Attempt delete -> must be rejected with 422
        $resReject = $this->deleteJson("/api/v1/store/services/{$spUsed->id}");
        $resReject->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Layanan masih dipakai');

        $this->assertDatabaseHas('service_prices', [
            'id' => $spUsed->id,
        ]);

        // 2. ServicePrice yang belum pernah dipakai di order_items -> sukses dihapus
        $serviceUnused = Service::firstOrCreate(
            ['name' => 'Cuci Karpet Spesial'],
            ['description' => 'Cuci karpet bulu tebal', 'is_active' => true]
        );

        $spUnused = ServicePrice::create([
            'store_id' => $this->storeA->id,
            'service_id' => $serviceUnused->id,
            'price_per_unit' => 50000,
            'unit' => 'item',
            'is_active' => true,
        ]);

        $resDelete = $this->deleteJson("/api/v1/store/services/{$spUnused->id}");
        $resDelete->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('service_prices', [
            'id' => $spUnused->id,
        ]);
    }

    public function test_courier_claim_task_is_atomic_and_rejects_second_claim(): void
    {
        // Buat kurir kedua di Store A yang sama
        $courierUserA2 = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);
        $courierRole = Role::where('name', 'courier')->first();
        if ($courierRole) {
            $courierUserA2->roles()->attach($courierRole->id);
        }

        Courier::factory()->create([
            'user_id' => $courierUserA2->id,
            'store_id' => $this->storeA->id,
            'is_verified' => true,
            'is_available' => true,
        ]);

        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'courier_id' => null,
            'status' => 'pending_pickup',
        ]);

        // 1. Klaim pertama oleh Courier A -> Berhasil 200
        Sanctum::actingAs($this->courierUserA, ['role:courier']);
        $resClaim1 = $this->postJson("/api/v1/courier/tasks/{$order->id}/claim");
        $resClaim1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.courier_id', (string) $this->courierUserA->id);

        $order->refresh();
        $this->assertEquals($this->courierUserA->id, $order->courier_id);
        $this->assertEquals('pickup_assigned', $order->status);

        // 2. Klaim kedua oleh Courier A2 pada order yang sama -> Ditolak 409
        Sanctum::actingAs($courierUserA2, ['role:courier']);
        $resClaim2 = $this->postJson("/api/v1/courier/tasks/{$order->id}/claim");
        $resClaim2->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tugas ini sudah diambil oleh kurir lain.');

        // Pastikan kepemilikan tetap milik Courier A
        $order->refresh();
        $this->assertEquals($this->courierUserA->id, $order->courier_id);
    }

    public function test_courier_transition_rejects_unauthorized_courier_and_invalid_status_jump(): void
    {
        $order = Order::factory()->create([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customer->id,
            'courier_id' => $this->courierUserA->id,
            'status' => 'pickup_assigned',
        ]);

        // 1. Order tidak ditemukan -> 404
        Sanctum::actingAs($this->courierUserA, ['role:courier']);
        $this->postJson('/api/v1/courier/tasks/999999/transition', ['to_status' => 'picked_up'])
            ->assertStatus(404);

        // 2. Kurir lain (Courier B) mencoba transisi order milik Courier A -> 403
        Sanctum::actingAs($this->courierUserB, ['role:courier']);
        $resForbidden = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'picked_up',
        ]);
        $resForbidden->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Anda tidak memiliki hak akses untuk mengubah tugas pesanan ini.');

        // 3. Kurir sah (Courier A) mencoba lompatan status tidak valid (pickup_assigned -> completed) -> 422
        Sanctum::actingAs($this->courierUserA, ['role:courier']);
        $resInvalidJump = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'completed',
        ]);
        $resInvalidJump->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Kurir sah melakukan transisi valid: pickup_assigned -> picked_up -> 200
        $resValid1 = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'picked_up',
        ]);
        $resValid1->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('picked_up', $order->fresh()->status);

        // 5. Lanjut ke at_store -> 200
        $resValid2 = $this->postJson("/api/v1/courier/tasks/{$order->id}/transition", [
            'to_status' => 'at_store',
        ]);
        $resValid2->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('at_store', $order->fresh()->status);
    }

    public function test_mitra_can_reject_candidate_writes_audit_log_and_removes_from_available(): void
    {
        $candidateUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        $candidate = Courier::factory()->create([
            'user_id' => $candidateUser->id,
            'store_id' => null,
            'is_verified' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        // Verify candidate appears in available list before reject
        $listBefore = $this->getJson('/api/v1/store-admin/couriers');
        $listBefore->assertStatus(200);
        $candidatesBefore = collect($listBefore->json('data.available_candidates'))->pluck('courier_profile_id');
        $this->assertTrue($candidatesBefore->contains((string) $candidate->id));

        // Mitra A rejects candidate
        $res = $this->postJson("/api/v1/store-admin/couriers/reject/{$candidate->id}", [
            'reason' => 'Kuota kurir untuk gerai ini sudah mencukupi.',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lamaran kurir ditolak.')
            ->assertJsonPath('data.courier_profile_id', (string) $candidate->id);

        // (b) Verify candidate status UNCHANGED in DB (still verified & available)
        $freshCandidate = $candidate->fresh();
        $this->assertTrue((bool) $freshCandidate->is_verified);
        $this->assertTrue((bool) $freshCandidate->is_available);
        $this->assertNull($freshCandidate->store_id);

        // (c) Verify audit log recorded with actor_id and store_id in after_value
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->mitraUserA->id,
            'action' => 'STORE_COURIER_REJECT',
            'resource_type' => 'courier',
            'resource_id' => $candidate->id,
        ]);

        // (a) Verify candidate no longer appears in available candidates list of Store A
        $listAfterA = $this->getJson('/api/v1/store-admin/couriers');
        $listAfterA->assertStatus(200);
        $candidatesAfterA = collect($listAfterA->json('data.available_candidates'))->pluck('courier_profile_id');
        $this->assertFalse($candidatesAfterA->contains((string) $candidate->id));

        // (b) Verify candidate STILL appears in available candidates list of Store B
        Sanctum::actingAs($this->mitraUserB, ['role:store_admin']);
        $listAfterB = $this->getJson('/api/v1/store-admin/couriers');
        $listAfterB->assertStatus(200);
        $candidatesAfterB = collect($listAfterB->json('data.available_candidates'))->pluck('courier_profile_id');
        $this->assertTrue($candidatesAfterB->contains((string) $candidate->id));
    }

    public function test_mitra_cannot_reject_courier_belonging_to_another_store(): void
    {
        $courierBUser = User::factory()->create(['role' => User::ROLE_COURIER]);
        $courierB = Courier::factory()->create([
            'user_id' => $courierBUser->id,
            'store_id' => $this->storeB->id,
            'is_verified' => true,
            'is_available' => true,
        ]);

        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        $res = $this->postJson("/api/v1/store-admin/couriers/reject/{$courierB->id}", [
            'reason' => 'Bukan kurir toko saya',
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Anda tidak memiliki izin untuk menolak pelamar kurir dari toko lain.');
    }

    public function test_reject_courier_with_invalid_id_returns_404(): void
    {
        Sanctum::actingAs($this->mitraUserA, ['role:store_admin']);

        $res = $this->postJson('/api/v1/store-admin/couriers/reject/999999', [
            'reason' => 'ID tidak ada',
        ]);

        $res->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kandidat kurir tidak ditemukan.');
    }
}
