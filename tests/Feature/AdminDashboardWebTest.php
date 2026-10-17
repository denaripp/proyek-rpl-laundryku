<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function makeUser(string $roleName): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $roleName)->first());
        return $user;
    }

    // =========================================================================
    // 1. Authorization: Non-admin users blocked from ALL admin surfaces
    // =========================================================================

    public function test_customer_cannot_access_any_admin_tab_or_action(): void
    {
        $customer = $this->makeUser('customer');
        $store = Store::factory()->create(['status' => 'submitted']);

        // Gated tabs
        $this->actingAs($customer)->get('/dash/admin')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=stores')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=payments')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=refunds')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=disputes')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=audit')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=commission')->assertStatus(403);
        $this->actingAs($customer)->get('/dash/admin?tab=ai')->assertStatus(403);

        // Gated actions
        $this->actingAs($customer)->post("/dash/admin/stores/{$store->id}/approve")->assertStatus(403);
        $this->actingAs($customer)->post("/dash/admin/stores/{$store->id}/reject", ['reason' => 'test'])->assertStatus(403);
        $this->actingAs($customer)->post("/dash/admin/stores/{$store->id}/suspend", ['reason' => 'test'])->assertStatus(403);
        $this->actingAs($customer)->post("/dash/admin/stores/{$store->id}/reactivate")->assertStatus(403);
    }

    public function test_store_owner_cannot_access_any_admin_tab_or_action(): void
    {
        $storeOwner = $this->makeUser('store_owner');
        $store = Store::factory()->create(['status' => 'submitted']);

        // Gated tabs
        $this->actingAs($storeOwner)->get('/dash/admin')->assertStatus(403);
        $this->actingAs($storeOwner)->get('/dash/admin?tab=payments')->assertStatus(403);
        $this->actingAs($storeOwner)->get('/dash/admin?tab=refunds')->assertStatus(403);
        $this->actingAs($storeOwner)->get('/dash/admin?tab=disputes')->assertStatus(403);
        $this->actingAs($storeOwner)->get('/dash/admin?tab=audit')->assertStatus(403);

        // Gated actions
        $this->actingAs($storeOwner)->post("/dash/admin/stores/{$store->id}/approve")->assertStatus(403);
        $this->actingAs($storeOwner)->post("/dash/admin/stores/{$store->id}/reject", ['reason' => 'test'])->assertStatus(403);
        $this->actingAs($storeOwner)->post("/dash/admin/stores/{$store->id}/suspend", ['reason' => 'test'])->assertStatus(403);
        $this->actingAs($storeOwner)->post("/dash/admin/stores/{$store->id}/reactivate")->assertStatus(403);
    }

    // =========================================================================
    // 2. Admin & Owner: Allowed to view tabs
    // =========================================================================

    public function test_admin_can_access_overview_and_tabs(): void
    {
        $admin = $this->makeUser('platform_admin');

        // Overview
        $r = $this->actingAs($admin)->get('/dash/admin');
        $r->assertStatus(200);
        $r->assertSee('Platform Admin Overview');
        $r->assertSee('GMV Hari Ini');

        // Stores tab
        $rStores = $this->actingAs($admin)->get('/dash/admin?tab=stores');
        $rStores->assertStatus(200);
        $rStores->assertSee('Manajemen Toko');

        // Orders tab
        $rOrders = $this->actingAs($admin)->get('/dash/admin?tab=orders');
        $rOrders->assertStatus(200);
        $rOrders->assertSee('Daftar Seluruh Pesanan');

        // Customers tab
        $rCust = $this->actingAs($admin)->get('/dash/admin?tab=customers');
        $rCust->assertStatus(200);
        $rCust->assertSee('Daftar Pengguna Platform');

        // Couriers tab
        $rCour = $this->actingAs($admin)->get('/dash/admin?tab=couriers');
        $rCour->assertStatus(200);
        $rCour->assertSee('Daftar Mitra Kurir');

        // Payments tab
        $rPay = $this->actingAs($admin)->get('/dash/admin?tab=payments');
        $rPay->assertStatus(200);
        $rPay->assertSee('Riwayat Pembayaran');

        // Refunds tab
        $rRef = $this->actingAs($admin)->get('/dash/admin?tab=refunds');
        $rRef->assertStatus(200);
        $rRef->assertSee('Daftar Pengembalian Dana');

        // Disputes tab
        $rDisp = $this->actingAs($admin)->get('/dash/admin?tab=disputes');
        $rDisp->assertStatus(200);
        $rDisp->assertSee('Penyelesaian Sengketa Pesanan');

        // Audit tab
        $rAudit = $this->actingAs($admin)->get('/dash/admin?tab=audit');
        $rAudit->assertStatus(200);
        $rAudit->assertSee('Audit Log Aktivitas Sistem');
    }

    public function test_platform_owner_can_access_commission_and_ai_tabs(): void
    {
        $owner = $this->makeUser('platform_owner');

        $rComm = $this->actingAs($owner)->get('/dash/admin?tab=commission');
        $rComm->assertStatus(200);
        $rComm->assertSee('Pengaturan Komisi Platform');

        $rAi = $this->actingAs($owner)->get('/dash/admin?tab=ai');
        $rAi->assertStatus(200);
        $rAi->assertSee('Monitoring Penggunaan OpenAI Assistant');
    }

    // =========================================================================
    // 3. Admin Store Actions + Audit Log Verification
    // =========================================================================

    public function test_admin_can_approve_store_and_logs_audit_with_admin_user_id(): void
    {
        $admin = $this->makeUser('platform_admin');
        $owner = $this->makeUser('customer');

        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Laundry Barokah',
            'status' => 'submitted',
            'is_active' => false,
        ]);
        \App\Models\StoreApplication::create([
            'store_id' => $store->id,
            'submitted_at' => now(),
        ]);

        $res = $this->actingAs($admin)->post("/dash/admin/stores/{$store->id}/approve");
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $this->assertEquals('approved', $store->fresh()->status);
        $this->assertTrue((bool) $store->fresh()->is_active);

        // Verify Audit Log records action and admin_user_id
        $audit = AuditLog::where('resource_type', 'store')
            ->where('resource_id', $store->id)
            ->where('action', 'store_approved')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($admin->id, $audit->actor_id);
        $this->assertEquals($admin->id, $audit->metadata['admin_user_id'] ?? null);
    }

    public function test_admin_can_reject_store_and_logs_audit(): void
    {
        $admin = $this->makeUser('platform_admin');
        $owner = $this->makeUser('customer');

        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Laundry Ditolak',
            'status' => 'submitted',
            'is_active' => false,
        ]);
        \App\Models\StoreApplication::create([
            'store_id' => $store->id,
            'submitted_at' => now(),
        ]);

        $res = $this->actingAs($admin)->post("/dash/admin/stores/{$store->id}/reject", [
            'reason' => 'Nomor telepon tidak valid dan data tidak lengkap',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $this->assertEquals('rejected', $store->fresh()->status);
        $this->assertFalse((bool) $store->fresh()->is_active);

        // Verify Audit Log
        $audit = AuditLog::where('resource_type', 'store')
            ->where('resource_id', $store->id)
            ->where('action', 'store_rejected')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($admin->id, $audit->actor_id);
        $this->assertEquals('Nomor telepon tidak valid dan data tidak lengkap', $audit->metadata['reason'] ?? null);
    }

    public function test_admin_can_suspend_and_reactivate_store_with_audit_logs(): void
    {
        $admin = $this->makeUser('platform_admin');
        $owner = $this->makeUser('customer');

        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Toko Laundry Suspend Test',
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Suspend
        $res = $this->actingAs($admin)->post("/dash/admin/stores/{$store->id}/suspend", [
            'reason' => 'Pelanggaran ketentuan operasional',
        ]);
        $res->assertRedirect();
        $this->assertEquals('suspended', $store->fresh()->status);
        $this->assertFalse((bool) $store->fresh()->is_active);

        $suspendAudit = AuditLog::where('resource_type', 'store')
            ->where('resource_id', $store->id)
            ->where('action', 'store_suspended')
            ->latest()
            ->first();
        $this->assertNotNull($suspendAudit);
        $this->assertEquals($admin->id, $suspendAudit->actor_id);

        // Reactivate
        $res2 = $this->actingAs($admin)->post("/dash/admin/stores/{$store->id}/reactivate");
        $res2->assertRedirect();
        $this->assertEquals('approved', $store->fresh()->status);
        $this->assertTrue((bool) $store->fresh()->is_active);

        $reactivateAudit = AuditLog::where('resource_type', 'store')
            ->where('resource_id', $store->id)
            ->where('action', 'store_reactivated')
            ->latest()
            ->first();
        $this->assertNotNull($reactivateAudit);
        $this->assertEquals($admin->id, $reactivateAudit->actor_id);
    }
}
