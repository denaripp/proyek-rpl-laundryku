<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $adminRole = Role::where('name', 'platform_owner')->first() ?? Role::where('name', 'platform_admin')->first();
        if ($adminRole) {
            $this->admin->roles()->attach($adminRole->id);
        }

        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_cannot_access_any_admin_portal_routes(): void
    {
        $routes = [
            '/api/v1/admin/orders',
            '/api/v1/admin/payments',
            '/api/v1/admin/stores',
            '/api/v1/admin/users',
            '/api/v1/admin/categories',
            '/api/v1/admin/platform-settings/commission',
            '/api/v1/admin/audit-logs',
        ];

        foreach ($routes as $route) {
            $this->getJson($route)->assertStatus(401);
        }
    }

    public function test_customer_cannot_access_any_admin_portal_routes(): void
    {
        Sanctum::actingAs($this->customer, ['role:customer']);

        $routes = [
            '/api/v1/admin/orders',
            '/api/v1/admin/payments',
            '/api/v1/admin/stores',
            '/api/v1/admin/users',
            '/api/v1/admin/categories',
            '/api/v1/admin/platform-settings/commission',
            '/api/v1/admin/audit-logs',
        ];

        foreach ($routes as $route) {
            $this->getJson($route)->assertStatus(403);
        }
    }

    public function test_super_admin_can_access_all_admin_endpoints(): void
    {
        Sanctum::actingAs($this->admin, ['role:super_admin']);

        // 1. Orders
        $orderRes = $this->getJson('/api/v1/admin/orders');
        $orderRes->assertStatus(200)->assertJson(['success' => true]);

        // 2. Payments
        $payRes = $this->getJson('/api/v1/admin/payments');
        $payRes->assertStatus(200)->assertJson(['success' => true]);

        // 3. Stores
        $storeRes = $this->getJson('/api/v1/admin/stores');
        $storeRes->assertStatus(200)->assertJson(['success' => true]);

        // 4. Users
        $userRes = $this->getJson('/api/v1/admin/users');
        $userRes->assertStatus(200)->assertJson(['success' => true]);

        // 5. Categories
        $catRes = $this->getJson('/api/v1/admin/categories');
        $catRes->assertStatus(200)->assertJson(['success' => true]);

        // 6. Commission
        $commRes = $this->getJson('/api/v1/admin/platform-settings/commission');
        $commRes->assertStatus(200)->assertJson([
            'success' => true,
            'data' => [
                'key' => 'platform_commission_rate',
            ],
        ]);

        // 7. Audit Logs
        $auditRes = $this->getJson('/api/v1/admin/audit-logs');
        $auditRes->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_super_admin_can_approve_and_reject_store(): void
    {
        Sanctum::actingAs($this->admin, ['role:super_admin']);

        $store = Store::factory()->create([
            'owner_user_id' => $this->customer->id,
            'name' => 'Toko Baru Test',
            'status' => 'submitted',
            'is_active' => false,
        ]);

        // Approve
        $approveRes = $this->postJson("/api/v1/admin/stores/{$store->id}/approve", [
            'note' => 'Dokumen lengkap dan valid.',
        ]);
        $approveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'approval_status' => 'approved',
                    'operational_status' => 'active',
                ],
            ]);

        $this->assertEquals('approved', $store->fresh()->status);
        $this->assertTrue($store->fresh()->is_active);

        $storeToReject = Store::factory()->create([
            'owner_user_id' => $this->customer->id,
            'name' => 'Toko Reject Test',
            'status' => 'submitted',
            'is_active' => false,
        ]);

        // Reject
        $rejectRes = $this->postJson("/api/v1/admin/stores/{$storeToReject->id}/reject", [
            'reason' => 'Perlu revisi surat izin.',
        ]);
        $rejectRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'approval_status' => 'rejected',
                ],
            ]);

        $this->assertEquals('rejected', $storeToReject->fresh()->status);
    }

    public function test_super_admin_can_update_user_status_commission_and_manage_categories(): void
    {
        Sanctum::actingAs($this->admin, ['role:super_admin']);

        // Update user status
        $statusRes = $this->patchJson("/api/v1/admin/users/{$this->customer->id}/status", [
            'status' => 'suspended',
            'reason' => 'Melanggar ketentuan layanan.',
        ]);
        $statusRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'suspended',
                ],
            ]);
        $this->assertFalse($this->customer->fresh()->is_active);

        // Update commission
        $commRes = $this->patchJson('/api/v1/admin/platform-settings/commission', [
            'amount' => 12.5,
            'description' => 'Update komisi promo',
        ]);
        $commRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 12.5,
                ],
            ]);

        // Create category
        $catCreate = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Dry Clean Premium',
            'status' => 'active',
        ]);
        $catCreate->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Dry Clean Premium',
                    'slug' => 'dry-clean-premium',
                    'status' => 'active',
                ],
            ]);
        $catId = $catCreate->json('data.id');

        // Update category
        $catUpdate = $this->putJson("/api/v1/admin/categories/{$catId}", [
            'name' => 'Dry Clean Super Premium',
            'status' => 'inactive',
        ]);
        $catUpdate->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Dry Clean Super Premium',
                    'status' => 'inactive',
                ],
            ]);

        // Delete category
        $catDel = $this->deleteJson("/api/v1/admin/categories/{$catId}");
        $catDel->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_delete_category_fails_when_in_use_and_can_deactivate(): void
    {
        Sanctum::actingAs($this->admin, ['role:super_admin']);

        // 1. Buat service yang dipakai oleh store price
        $service = Service::create([
            'name' => 'Cuci Kilat Dipakai',
            'is_active' => true,
        ]);

        $store = Store::factory()->create();
        ServicePrice::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'price_per_unit' => 15000,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        // Kasus 1: Dihapus saat masih dipakai -> gagal 422 "Kategori masih dipakai"
        $delFail = $this->deleteJson("/api/v1/admin/categories/{$service->id}");
        $delFail->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Kategori masih dipakai',
            ]);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'deleted_at' => null]);

        // Kasus 2: Dihapus dengan opsi deactivate=true -> berhasil dinonaktifkan
        $deactivateRes = $this->deleteJson("/api/v1/admin/categories/{$service->id}?deactivate=1");
        $deactivateRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => (string) $service->id,
                    'is_active' => false,
                ],
            ]);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'deleted_at' => null]);
    }
}

