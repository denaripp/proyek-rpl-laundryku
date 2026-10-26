<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    public function test_store_admin_cannot_access_customer_portal_endpoints(): void
    {
        $storeAdmin = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        $storeRole = Role::where('name', 'store_admin')->first();
        if ($storeRole) {
            $storeAdmin->roles()->attach($storeRole->id, ['assigned_at' => now()]);
        }

        Sanctum::actingAs($storeAdmin, ['role:store_admin']);

        // Akses ke portal customer harus ditolak (403 Forbidden)
        $this->getJson('/api/v1/customer/profile')->assertStatus(403);
        $this->getJson('/api/v1/customer/orders')->assertStatus(403);
    }

    public function test_customer_cannot_access_store_admin_portal_endpoints(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        // Akses ke portal mitra/toko harus ditolak (403 Forbidden)
        $this->getJson('/api/v1/mitra/store')->assertStatus(403);
        $this->getJson('/api/v1/mitra/orders')->assertStatus(403);
        $this->getJson('/api/v1/store/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/store/services')->assertStatus(403);
    }

    public function test_customer_cannot_access_courier_portal_endpoints(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['role:customer']);

        // Akses ke portal kurir harus ditolak (403 Forbidden)
        $this->getJson('/api/v1/courier/profile')->assertStatus(403);
        $this->getJson('/api/v1/courier/tasks')->assertStatus(403);
    }

    public function test_store_admin_cannot_access_super_admin_portal_endpoints(): void
    {
        $storeAdmin = User::factory()->create([
            'role' => User::ROLE_STORE_ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($storeAdmin, ['role:store_admin']);

        // Akses ke portal super admin harus ditolak (403 Forbidden)
        $this->getJson('/api/v1/admin/overview')->assertStatus(403);
        $this->getJson('/api/v1/admin/stores')->assertStatus(403);
    }

    public function test_switch_mode_endpoint_does_not_exist(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['role:customer']);

        // Backend tidak menyediakan endpoint switch mode peran
        $response = $this->postJson('/api/v1/me/mode', ['mode' => 'store']);
        $this->assertEquals(404, $response->status());
    }

    public function test_frontend_layouts_do_not_contain_mode_switcher_elements(): void
    {
        $dashboardLayoutPath = base_path('frontend/components/layout/DashboardLayout.tsx');
        $publicLayoutPath = base_path('frontend/components/layout/PublicLayout.tsx');

        $this->assertFileExists($dashboardLayoutPath);
        $this->assertFileExists($publicLayoutPath);

        $dashboardContent = file_get_contents($dashboardLayoutPath);
        $publicContent = file_get_contents($publicLayoutPath);

        // Pastikan tidak ada string "Mode Pelanggan", "Mode Toko", "Mode Kurir"
        $this->assertStringNotContainsString('Mode Pelanggan', $dashboardContent);
        $this->assertStringNotContainsString('Mode Toko', $dashboardContent);
        $this->assertStringNotContainsString('Mode Kurir', $dashboardContent);

        $this->assertStringNotContainsString('Mode Pelanggan', $publicContent);
        $this->assertStringNotContainsString('Mode Toko', $publicContent);
        $this->assertStringNotContainsString('Mode Kurir', $publicContent);

        // Pastikan pemanggilan switchUserMode sudah dihapus dari kedua layout
        $this->assertStringNotContainsString('switchUserMode', $dashboardContent);
        $this->assertStringNotContainsString('switchUserMode', $publicContent);

        // Pastikan lib auth juga tidak lagi mengekspor switchUserMode atau menyimpan mode fields
        $authPath = base_path('frontend/lib/auth.ts');
        $this->assertFileExists($authPath);
        $authContent = file_get_contents($authPath);
        $this->assertStringNotContainsString('switchUserMode', $authContent);
        $this->assertStringNotContainsString('active_mode', $authContent);
        $this->assertStringNotContainsString('available_modes', $authContent);
    }
}
