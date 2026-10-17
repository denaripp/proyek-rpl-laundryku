<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * F-1.1 Security test — 2FA enforcement on internal dashboards.
 *
 * Verifies that all /dash/* routes (Tahap 18 internal dashboards) require 2FA
 * verification when user has two_factor_enabled = true, matching the
 * behavior of /dashboard alias.
 */
class DashboardTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    /**
     * Helper: build user with role + optional 2FA setup.
     */
    protected function makeUser(string $roleName, bool $twoFactor = false, array $attrs = []): User
    {
        $userAttrs = ['is_active' => true];
        if ($twoFactor) {
            $userAttrs['two_factor_enabled'] = true;
            $userAttrs['google2fa_secret'] = 'JBSWY3DPEHPK3PXP';
        }
        $userAttrs = array_merge($userAttrs, $attrs);
        $user = User::factory()->create($userAttrs);
        $user->roles()->attach(Role::where('name', $roleName)->first());
        return $user;
    }

    // ============ A. Unauthenticated → redirect to login ============

    public function test_guest_redirected_to_login_for_all_dash_routes(): void
    {
        foreach (['/dash', '/dash/store', '/dash/courier', '/dash/admin', '/dashboard'] as $url) {
            $r = $this->get($url);
            $this->assertContains($r->status(), [302, 401], "URL {$url} should redirect when guest");
            if ($r->status() === 302) {
                $this->assertStringContainsString('login', $r->headers->get('Location', ''), "URL {$url} should redirect to login");
            }
        }
    }

    // ============ B. User with 2FA enabled + pending_2fa → /2fa/verify ============

    public function test_2fa_user_redirected_to_verify_for_dash_routes(): void
    {
        $admin = $this->makeUser('platform_admin', twoFactor: true);

        $this->actingAs($admin);
        $this->session(['pending_2fa' => true]);

        // 2FA user trying to access /dash/* BEFORE 2FA verify → must redirect to /2fa/verify
        foreach (['/dash', '/dash/store', '/dash/courier', '/dash/admin', '/dashboard'] as $url) {
            $r = $this->get($url);
            $this->assertEquals(302, $r->status(), "URL {$url} should redirect");
            $this->assertEquals(
                '/2fa/verify',
                parse_url($r->headers->get('Location', ''), PHP_URL_PATH),
                "URL {$url} must redirect to /2fa/verify, got " . $r->headers->get('Location', '')
            );
        }
    }

    public function test_2fa_user_post_dash_availability_also_protected(): void
    {
        $courierUser = $this->makeUser('courier', twoFactor: true);
        \App\Models\Courier::create([
            'user_id' => $courierUser->id, 'is_available' => true, 'is_verified' => true,
        ]);

        $this->actingAs($courierUser);
        $this->session(['pending_2fa' => true]);

        $r = $this->post('/dash/courier/availability', ['is_available' => false]);
        $this->assertEquals(302, $r->status());
        $this->assertEquals(
            '/2fa/verify',
            parse_url($r->headers->get('Location', ''), PHP_URL_PATH),
            'POST /dash/courier/availability must redirect to /2fa/verify when 2FA pending'
        );
    }

    // ============ C. 2FA verified → /dash/* accessible by role ============

    public function test_2fa_verified_user_can_access_dash_routes_by_role(): void
    {
        // Admin
        $admin = $this->makeUser('platform_admin', twoFactor: true);
        $this->actingAs($admin);
        // pending_2fa is NOT set (already verified)

        $this->get('/dash/admin')->assertStatus(200);
        $this->get('/dash')->assertStatus(302); // redirects to admin.dashboard

        // Store owner
        $owner = $this->makeUser('store_owner', twoFactor: true);
        Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved', 'is_active' => true,
        ]);
        $this->actingAs($owner);
        $this->get('/dash/store')->assertStatus(200);
        $this->get('/dash/admin')->assertStatus(403);

        // Courier
        $courierUser = $this->makeUser('courier', twoFactor: true);
        \App\Models\Courier::create([
            'user_id' => $courierUser->id, 'is_available' => true, 'is_verified' => true,
        ]);
        $this->actingAs($courierUser);
        $this->get('/dash/courier')->assertStatus(200);
        $this->get('/dash/admin')->assertStatus(403);
    }

    // ============ D. User without permission → 403 (after 2FA verified) ============

    public function test_user_without_role_permission_gets_403_on_dash_admin(): void
    {
        $customer = $this->makeUser('customer', twoFactor: true);
        $this->actingAs($customer);

        $this->get('/dash/admin')->assertStatus(403);
        $this->get('/dash/store')->assertStatus(403);
        $this->get('/dash/courier')->assertStatus(403);
    }

    // ============ E. User WITHOUT 2FA enabled → behavior preserved ============

    public function test_user_without_2fa_can_access_dash_routes_directly(): void
    {
        // 2FA disabled user: pending_2fa should NOT exist in session
        $admin = $this->makeUser('platform_admin', twoFactor: false);
        $this->actingAs($admin);
        // No session(['pending_2fa' => true]) — should access /dash/admin without 2FA

        $this->get('/dash/admin')->assertStatus(200);
    }

    // ============ F. Full 2FA flow: login → 2fa verify → access dashboard ============

    public function test_full_2fa_flow_login_verify_access_dash(): void
    {
        $google2fa = new Google2FA();
        $validSecret = 'JBSWY3DPEHPK3PXP';

        $admin = $this->makeUser('platform_admin', false, [
            'two_factor_enabled' => true,
            'google2fa_secret' => $validSecret,
        ]);

        // Step 1: Login
        $r = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $r->assertRedirect('/2fa/verify');

        // Step 2: Try /dash/admin BEFORE 2FA verify → /2fa/verify
        $this->get('/dash/admin')->assertRedirect('/2fa/verify');

        // Step 3: Get current OTP
        $otp = $google2fa->getCurrentOtp($validSecret);

        // Step 4: Verify 2FA
        $this->post('/2fa/verify', ['code' => $otp])->assertRedirect('/dashboard');

        // Step 5: Now /dash/admin should be accessible (role-based)
        $this->get('/dash/admin')->assertStatus(200);
    }
}
