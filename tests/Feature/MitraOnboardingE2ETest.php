<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MitraOnboardingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_mitra_onboarding_lifecycle_via_http_flow()
    {
        // 0. Seed base roles, permissions & default services
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $service = Service::create([
            'name' => 'Cuci Komplit Reguler',
            'slug' => 'cuci-komplit-reguler',
            'category' => 'kiloan',
            'unit' => 'kg',
            'estimated_duration_hours' => 48,
            'is_active' => true,
        ]);

        // Create Platform Owner user for approving
        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@laundryku.test',
            'password' => \Illuminate\Support\Facades\Hash::make('admin12345'),
            'is_active' => true,
        ]);
        $platformOwnerRole = Role::where('name', 'platform_owner')->first();
        $admin->roles()->attach($platformOwnerRole);

        // -------------------------------------------------------------------
        // STEP 1: Guest visits /onboard & clicks "Daftarkan Laundry"
        // -------------------------------------------------------------------
        $r1 = $this->get('/onboard');
        $r1->assertStatus(200);
        $r1->assertSee('Daftarkan Laundry Anda ke LaundryKu');
        $r1->assertSee(route('google.redirect', ['next' => route('store.onboard')]));

        // -------------------------------------------------------------------
        // STEP 2: Guest clicks Google Login redirect with next=/onboard
        // -------------------------------------------------------------------
        $r2 = $this->get('/auth/google?next=/onboard');
        $r2->assertStatus(302);
        $this->assertSame('/onboard', session('oauth_next'));

        // -------------------------------------------------------------------
        // STEP 3: Google Callback receives new user & creates account
        // -------------------------------------------------------------------
        $mockSocialiteUser = Mockery::mock(SocialiteUser::class);
        $mockSocialiteUser->shouldReceive('getId')->andReturn('google-user-999');
        $mockSocialiteUser->shouldReceive('getName')->andReturn('Budi Mitra');
        $mockSocialiteUser->shouldReceive('getEmail')->andReturn('budi.mitra@example.com');
        $mockSocialiteUser->shouldReceive('getAvatar')->andReturn(null);

        $mockProvider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $mockProvider->shouldReceive('user')->andReturn($mockSocialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($mockProvider);

        $r3 = $this->withSession(['oauth_next' => '/onboard'])->get('/auth/google/callback');
        $r3->assertRedirect('/onboard');

        // Verify user was created in DB with customer role
        $mitraUser = User::where('email', 'budi.mitra@example.com')->first();
        $this->assertNotNull($mitraUser);
        $this->assertTrue($mitraUser->hasRole('customer'));
        $this->assertAuthenticatedAs($mitraUser);

        // -------------------------------------------------------------------
        // STEP 4: Authenticated user views /onboard (renders actual form)
        // -------------------------------------------------------------------
        $r4 = $this->actingAs($mitraUser)->get('/onboard');
        $r4->assertStatus(200);
        $r4->assertSee('Formulir Pendaftaran Mitra Laundry');
        $r4->assertSee('1. Data Pemilik');
        $r4->assertSee('2. Profil Outlet Laundry');

        // -------------------------------------------------------------------
        // STEP 5: User submits the onboarding form (action=submit)
        // -------------------------------------------------------------------
        $formData = [
            'action' => 'submit',
            'owner_name' => 'Budi Mitra',
            'owner_email' => 'budi.mitra@example.com',
            'owner_phone' => '081298765432',
            'name' => 'Laundry Budi Bersih',
            'phone' => '081298765432',
            'whatsapp' => '081298765432',
            'description' => 'Jasa laundry kiloan dan satuan bersih wangi higienis.',
            'address' => 'Jl. Boulevard No. 88',
            'rt_rw' => '002/005',
            'kelurahan' => 'Kelapa Gading Barat',
            'kecamatan' => 'Kelapa Gading',
            'city' => 'Jakarta Utara',
            'province' => 'DKI Jakarta',
            'postal_code' => '14240',
            'latitude' => -6.155000,
            'longitude' => 106.900000,
            'service_radius_km' => 7.5,
            'minimum_order_amount' => 25000,
            'pickup_fee' => 5000,
            'delivery_fee' => 5000,
            'cod_enabled' => '1',
            'open_time' => '07:00',
            'close_time' => '21:00',
            'services' => [
                $service->id => [
                    'selected' => '1',
                    'price' => 9000,
                    'unit' => 'kg',
                ],
            ],
        ];

        $r5 = $this->actingAs($mitraUser)->post('/onboard', $formData);
        $r5->assertRedirect(route('store.onboard'));
        $r5->assertSessionHas('status', 'Pendaftaran berhasil! Toko Anda telah otomatis disetujui dan langsung aktif.');

        // -------------------------------------------------------------------
        // STEP 6: Verify Store is AUTO-APPROVED, is_active=true & store_owner role attached
        // -------------------------------------------------------------------
        $createdStore = Store::where('owner_user_id', $mitraUser->id)->first();
        $this->assertNotNull($createdStore);
        $this->assertSame('Laundry Budi Bersih', $createdStore->name);
        $this->assertSame('approved', $createdStore->status);
        $this->assertTrue((bool) $createdStore->is_active);

        // Verification of immediate store_owner role assignment
        $this->assertTrue($mitraUser->fresh()->hasRole('store_owner'));
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $mitraUser->id,
            'store_id' => $createdStore->id,
        ]);

        // Verify audit log has auto_approved flag
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'store',
            'resource_id' => $createdStore->id,
            'action' => 'store_auto_approved',
        ]);

        // -------------------------------------------------------------------
        // STEP 7: Check /onboard after auto-approval renders approved tracker
        // -------------------------------------------------------------------
        $r7 = $this->actingAs($mitraUser)->get('/onboard');
        $r7->assertStatus(200);
        $r7->assertSee('Laundry Anda Telah Disetujui!');
        $r7->assertSee(route('store.dashboard'));

        // -------------------------------------------------------------------
        // STEP 8: Check /dashboard redirects directly to Store Dashboard
        // -------------------------------------------------------------------
        $r8 = $this->actingAs($mitraUser)->get('/dashboard');
        $r8->assertRedirect(route('store.dashboard'));

        $r8store = $this->actingAs($mitraUser)->get(route('store.dashboard'));
        $r8store->assertStatus(200);
        $r8store->assertSee('Laundry Budi Bersih');

        // -------------------------------------------------------------------
        // STEP 9: Manual Approval Fallback (Artisan command) for non-auto-approved store
        // -------------------------------------------------------------------
        $manualStore = Store::factory()->create([
            'owner_user_id' => $mitraUser->id,
            'name' => 'Laundry Budi Cabang Manual',
            'status' => 'submitted',
            'is_active' => false,
        ]);
        \App\Models\StoreApplication::create([
            'store_id' => $manualStore->id,
            'submitted_at' => now(),
        ]);

        $exitCode = Artisan::call('store:approve', ['store_id' => $manualStore->id]);
        $this->assertSame(0, $exitCode);

        $approvedManual = $manualStore->fresh();
        $this->assertSame('approved', $approvedManual->status);
        $this->assertTrue((bool) $approvedManual->is_active);

        // -------------------------------------------------------------------
        // STEP 10: Re-login test (Logout -> Login -> /dashboard -> /dash/store)
        // -------------------------------------------------------------------
        $this->post('/logout');
        $this->assertGuest();

        // Login again via standard login
        $r11 = $this->post('/login', [
            'email' => 'budi.mitra@example.com',
            'password' => 'password', // will fail password since oauth user has random pwd
        ]);
        // For oauth user, re-login via OAuth callback
        $r11oauth = $this->get('/auth/google/callback');
        $r11oauth->assertRedirect('/dashboard');

        // Following redirect to /dashboard lands on /dash/store
        $this->assertAuthenticatedAs($mitraUser);
        $r11final = $this->get('/dashboard');
        $r11final->assertRedirect(route('store.dashboard'));
    }
}
