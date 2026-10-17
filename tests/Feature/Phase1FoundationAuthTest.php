<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CodCollection;
use App\Models\Courier;
use App\Models\CourierUser;
use App\Models\CustomerUser;
use App\Models\MitraUser;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1FoundationAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    /**
     * Test 1: GET /switch-role is completely 404 (banned feature).
     */
    public function test_switch_role_route_is_404(): void
    {
        $response = $this->get('/switch-role');
        $response->assertStatus(404);

        $response2 = $this->get('/switch');
        $response2->assertStatus(404);

        $response3 = $this->post('/switch-role');
        $response3->assertStatus(404);
    }

    /**
     * Test 2: Customer can log in via /login onto web guard.
     */
    public function test_customer_can_login_via_web_guard(): void
    {
        $customer = CustomerUser::create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi@customer.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'budi@customer.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertEquals($customer->id, Auth::guard('web')->id());
    }

    /**
     * Test 3: Customer credentials MUST FAIL when submitted to /mitra/login, /kurir/login, /admin/login.
     */
    public function test_customer_credentials_fail_on_other_doors(): void
    {
        CustomerUser::create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi@customer.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // Fail on Mitra login
        $mitraRes = $this->post(route('mitra.login'), [
            'email' => 'budi@customer.com',
            'password' => 'password123',
        ]);
        $mitraRes->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('mitra')->check());

        // Fail on Kurir login
        $kurirRes = $this->post(route('kurir.login'), [
            'email' => 'budi@customer.com',
            'password' => 'password123',
        ]);
        $kurirRes->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('kurir')->check());

        // Fail on Admin login
        $adminRes = $this->post(route('admin.login'), [
            'email' => 'budi@customer.com',
            'password' => 'password123',
        ]);
        $adminRes->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('admin')->check());
    }

    /**
     * Test 4: Mitra can register and login on mitra guard.
     */
    public function test_mitra_registration_and_login(): void
    {
        $regResponse = $this->post(route('mitra.register'), [
            'name' => 'Mitra Laundry Sejahtera',
            'email' => 'owner@laundrysejahtera.com',
            'phone' => '081234567890',
            'password' => 'mitrapass123',
            'password_confirmation' => 'mitrapass123',
        ]);

        $regResponse->assertRedirect(route('mitra.dashboard'));
        $this->assertTrue(Auth::guard('mitra')->check());

        // Logout
        $this->post(route('mitra.logout'))->assertRedirect(route('mitra.login'));
        $this->assertFalse(Auth::guard('mitra')->check());

        // Login again
        $loginRes = $this->post(route('mitra.login'), [
            'email' => 'owner@laundrysejahtera.com',
            'password' => 'mitrapass123',
        ]);
        $loginRes->assertRedirect(route('mitra.dashboard'));
        $this->assertTrue(Auth::guard('mitra')->check());
    }

    /**
     * Test 5: Same email address can exist as customer AND mitra (composite unique: email, role).
     */
    public function test_same_email_can_exist_for_customer_and_mitra_separately(): void
    {
        $sharedEmail = 'budi.shared@example.com';

        // Register as Customer
        $customer = CustomerUser::create([
            'name' => 'Budi Customer',
            'email' => $sharedEmail,
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('custpass123'),
            'is_active' => true,
        ]);

        // Register as Mitra with SAME email
        $mitra = MitraUser::create([
            'name' => 'Budi Mitra Owner',
            'email' => $sharedEmail,
            'role' => User::ROLE_STORE_ADMIN,
            'password' => Hash::make('mitrapass123'),
            'is_active' => true,
        ]);

        $this->assertNotEquals($customer->id, $mitra->id);
        $this->assertEquals(User::ROLE_CUSTOMER, $customer->role);
        $this->assertEquals(User::ROLE_STORE_ADMIN, $mitra->role);

        // Login customer
        $this->post(route('login'), [
            'email' => $sharedEmail,
            'password' => 'custpass123',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('mitra')->check());

        // Logout customer
        $this->post(route('logout'));

        // Login mitra
        $this->post(route('mitra.login'), [
            'email' => $sharedEmail,
            'password' => 'mitrapass123',
        ])->assertRedirect(route('mitra.dashboard'));
        $this->assertTrue(Auth::guard('mitra')->check());
        $this->assertFalse(Auth::guard('web')->check());
    }

    /**
     * Test 6: Courier has no public register endpoint.
     */
    public function test_courier_has_no_public_register(): void
    {
        $this->get('/kurir/register')->assertStatus(404);
        $this->post('/kurir/register')->assertStatus(404);
    }

    /**
     * Test 7: Super Admin requires mandatory TOTP 2FA.
     */
    public function test_super_admin_requires_totp_2fa(): void
    {
        // 1. Initial password check redirects to /admin/2fa
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@laundryku.test',
            'password' => 'admin12345',
        ]);

        $response->assertRedirect(route('admin.2fa'));
        $this->assertFalse(Auth::guard('admin')->check()); // Not fully authenticated yet

        // 2. Submit wrong OTP
        $wrongOtp = $this->post(route('admin.2fa.verify'), [
            'one_time_password' => '999999',
        ]);
        $wrongOtp->assertSessionHasErrors('one_time_password');
        $this->assertFalse(Auth::guard('admin')->check());

        // 3. Submit valid test OTP (123456 in test mode)
        $validOtp = $this->post(route('admin.2fa.verify'), [
            'one_time_password' => '123456',
        ]);
        $validOtp->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(Auth::guard('admin')->check());
    }

    /**
     * Test 8: Guest route protection and prefix redirection.
     */
    public function test_route_prefix_guest_redirection(): void
    {
        $this->get('/mitra/dashboard')->assertRedirect(route('mitra.login'));
        $this->get('/kurir/dashboard')->assertRedirect(route('kurir.login'));
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    /**
     * Test 9: Authenticated customer cannot access mitra or admin dashboard.
     */
    public function test_customer_cannot_render_mitra_or_admin_dashboard(): void
    {
        $customer = CustomerUser::create([
            'name' => 'Budi Pelanggan',
            'email' => 'budi2@customer.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'web');

        // Customer opening /mitra/dashboard is redirected to mitra login (guest of mitra guard)
        $this->get('/mitra/dashboard')->assertRedirect(route('mitra.login'));

        // Customer opening /admin/dashboard is redirected to admin login
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    /**
     * Test 10: Settings model reads, writes, and caches placeholder settings.
     */
    public function test_settings_model_persists_and_caches(): void
    {
        $this->assertEquals(500000, Setting::get('COD_MAX_AMOUNT'));
        $this->assertEquals(10, Setting::get('MARKETPLACE_COMMISSION_PERCENT'));

        Setting::set('COD_MAX_AMOUNT', 750000, 'integer');
        $this->assertEquals(750000, Setting::get('COD_MAX_AMOUNT'));
    }

    /**
     * Test 11: CodCollection model persists status transitions.
     */
    public function test_cod_collection_model(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'submitted',
            'payment_method' => 'cod',
            'total_amount' => 120000,
        ]);

        $cod = CodCollection::create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'amount' => 120000,
            'status' => CodCollection::STATUS_PENDING,
            'otp_code' => '654321',
        ]);

        $this->assertEquals(CodCollection::STATUS_PENDING, $cod->status);
        $this->assertFalse($cod->isCollected());

        $cod->status = CodCollection::STATUS_COLLECTED;
        $cod->verified_at = now();
        $cod->save();

        $this->assertTrue($cod->isCollected());
    }

    /**
     * Test 12: Landing page hides "Gabung Jadi Mitra" link for authenticated users.
     */
    public function test_landing_page_hides_gabung_mitra_for_logged_in_users(): void
    {
        // As guest: "Gabung Jadi Mitra" is visible
        $guestRes = $this->get(route('home'));
        $guestRes->assertSee('Gabung Jadi Mitra');

        // As Customer: "Gabung Jadi Mitra" is hidden
        $customer = CustomerUser::create([
            'name' => 'Budi Cust',
            'email' => 'budicust@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
        ]);
        $this->actingAs($customer, 'web');

        $authRes = $this->get(route('home'));
        $authRes->assertDontSee('Gabung Jadi Mitra');
    }
}
