<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_owner_cannot_be_created_publicly()
    {
        // Platform owner or privileged roles cannot be registered via public customer register
        $response = $this->post('/register', [
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertStatus(302);

        // Security check ensures no privileged user created from public register
        $user = User::where('email', 'owner@example.com')->first();
        if ($user) {
            $this->assertNotEquals('super_admin', $user->role);
            $this->assertFalse($user->hasRole('platform_owner'));
        }
    }

    public function test_customer_public_registration_creates_customer_user()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $response = $this->post('/register', [
            'name' => 'Customer Budi',
            'email' => 'budi.registered@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+628123456789',
        ]);
        $response->assertRedirect(route('customer.dashboard'));

        $newUser = User::where('email', 'budi.registered@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals(User::ROLE_CUSTOMER, $newUser->role);
        $this->assertEquals('+628123456789', $newUser->phone);
    }

    public function test_phone_column_exists_in_users_table()
    {
        // SECURITY (Tahap 22): the `phone` column was missing from users
        // table despite AuthController writing it. Migration added it.
        $this->assertTrue(\Schema::hasColumn('users', 'phone'));
    }

    public function test_login_respects_safe_next_param()
    {
        // SECURITY (Tahap 23): ?next= must redirect to a relative path
        // after successful login (so users can return to /onboard etc).
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $customer = \App\Models\User::factory()->create([
            'email' => 'returning@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'is_active' => true,
        ]);
        $customer->roles()->attach(\App\Models\Role::where('name', 'customer')->first());

        $r = $this->post('/login?next=/onboard', [
            'email' => 'returning@example.com',
            'password' => 'password123',
        ]);
        $r->assertRedirect('/onboard');
    }

    public function test_login_blocks_external_next_param()
    {
        // SECURITY (Tahap 23): defense against open redirect — next=
        // must NOT redirect to an external URL.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = \App\Models\User::factory()->create([
            'email' => 'safe@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach(\App\Models\Role::where('name', 'customer')->first());

        $r = $this->post('/login?next=https://evil.com/phish', [
            'email' => 'safe@example.com',
            'password' => 'password123',
        ]);
        // Should NOT redirect to external URL — fallback to dashboard.
        $this->assertStringNotContainsString('evil.com', $r->headers->get('Location', ''));
    }

    public function test_login_blocks_protocol_relative_next_param()
    {
        // SECURITY (Tahap 24): defense against open redirect via
        // protocol-relative URLs (//evil.com) and backslash-trick
        // (/\evil.com — some browsers normalise backslash to slash).
        // Both MUST be rejected; fallback to /dashboard.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = \App\Models\User::factory()->create([
            'email' => 'protocoltest@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach(\App\Models\Role::where('name', 'customer')->first());

        foreach (['//evil.com', '/\\evil.com'] as $badNext) {
            $r = $this->post('/login?next=' . urlencode($badNext), [
                'email' => 'protocoltest@example.com',
                'password' => 'password123',
            ]);
            $location = $r->headers->get('Location', '');
            $this->assertStringNotContainsString('evil.com', $location,
                "next={$badNext} must not redirect to evil.com (got: {$location})");
            // Fallback: must redirect to dashboard (relative URL).
            $this->assertTrue(
                str_contains($location, '/dashboard') || str_ends_with($location, '/dashboard'),
                "next={$badNext} should fall back to /dashboard (got: {$location})"
            );
        }
    }

    public function test_guest_can_access_onboard_page_with_google_cta()
    {
        $r = $this->get('/onboard');
        $r->assertStatus(200);
        $r->assertSee('Daftarkan Laundry');
        $r->assertSee(route('google.redirect', ['next' => route('store.onboard')]));
    }

    public function test_onboard_post_submission_requires_auth()
    {
        $r = $this->post('/onboard', ['name' => 'Laundry Test']);
        $r->assertRedirect(route('login'));
    }

    public function test_login_page_forwards_next_param_to_google_redirect()
    {
        $r = $this->get('/login?next=/onboard');
        $r->assertStatus(200);
        $r->assertSee(route('google.redirect', ['next' => '/onboard']));
    }

    public function test_google_redirect_stores_next_in_session_not_oauth_state()
    {
        // SECURITY (Tahap 25): the ?next= param for Google OAuth MUST
        // be stored in the SESSION, not in the OAuth `state` param.
        // The OAuth `state` is reserved for Socialite's CSRF token —
        // overriding it breaks the callback validation silently
        // (causing the "stuck loading" regression).
        $r = $this->get('/auth/google?next=/onboard');
        $r->assertStatus(302); // redirect to Google

        // Verify session has the next value, NOT the OAuth state.
        $this->assertSame('/onboard', session('oauth_next'));

        // The redirect URL should contain Google's own random state
        // (40-char), NOT our /onboard.
        $location = $r->headers->get('Location', '');
        $this->assertStringContainsString('state=', $location);
        // Our /onboard must NOT be in the state param value.
        $this->assertStringNotContainsString(urlencode('/onboard'), $location,
            'OAuth state must not contain our /onboard value (it would break CSRF check)');
    }

    public function test_two_factor_required_for_high_privilege_roles()
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $validSecret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'two_factor_enabled' => true,
            'google2fa_secret' => $validSecret,
            'is_active' => true,
        ]);

        // Assign platform_admin role
        $role = Role::create(['name' => 'platform_admin', 'display_name' => 'Platform Admin']);
        $user->roles()->attach($role);

        // Login
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/2fa/verify');

        // Try to access dashboard without 2FA
        $this->get('/dashboard')
            ->assertRedirect('/2fa/verify');

        // Get current OTP
        $otp = $google2fa->getCurrentOtp($validSecret);

        // Verify with correct OTP - should succeed
        $this->post('/2fa/verify', ['code' => $otp])
            ->assertRedirect('/dashboard');
    }

    public function test_2fa_user_cannot_access_protected_routes_without_verification()
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'google2fa_secret' => 'secret123',
        ]);

        $this->actingAs($user);
        $this->session(['pending_2fa' => true]);

        $this->get('/dashboard')
            ->assertRedirect('/2fa/verify');
    }

    public function test_mitra_user_with_approved_store_redirects_to_store_dashboard_on_login()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'email' => 'mitra.approved@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $store = \App\Models\Store::factory()->create([
            'owner_user_id' => $user->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $roleStoreOwner = Role::where('name', 'store_owner')->first();
        $user->roles()->attach($roleStoreOwner, ['store_id' => $store->id]);

        $this->actingAs($user);
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('store.dashboard'));
    }

    public function test_mitra_user_with_pending_store_redirects_to_onboard_tracker()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'email' => 'mitra.pending@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $store = \App\Models\Store::factory()->create([
            'owner_user_id' => $user->id,
            'status' => 'submitted',
            'is_active' => false,
        ]);
        $roleStoreOwner = Role::where('name', 'store_owner')->first();
        $user->roles()->attach($roleStoreOwner, ['store_id' => $store->id]);

        $this->actingAs($user);
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('store.onboard'));
    }

    public function test_customer_redirects_to_customer_dashboard()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'email' => 'regular.customer@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $roleCustomer = Role::where('name', 'customer')->first();
        $user->roles()->attach($roleCustomer);

        $this->actingAs($user);
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('customer.dashboard'));
    }

    public function test_store_owner_role_is_auto_synced_on_login()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'email' => 'mitra.unsynced@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $store = \App\Models\Store::factory()->create([
            'owner_user_id' => $user->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasRole('store_owner'));

        $this->post('/login', [
            'email' => 'mitra.unsynced@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue($user->fresh()->hasRole('store_owner'));
    }
}