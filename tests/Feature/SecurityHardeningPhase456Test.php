<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiDataQueryService;
use App\Services\Ai\AiRateLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningPhase456Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_user_model_hides_google2fa_secret_from_array_and_json(): void
    {
        $user = User::factory()->create([
            'google2fa_secret' => 'SUPERSECRET2FAKEYXYZ123',
        ]);

        $array = $user->toArray();
        $this->assertArrayNotHasKey('google2fa_secret', $array);

        $json = json_encode($user);
        $this->assertStringNotContainsString('SUPERSECRET2FAKEYXYZ123', $json);
    }

    public function test_security_headers_are_attached_to_responses(): void
    {
        $response = $this->get('/up');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_unapproved_store_cannot_view_courier_applicants_in_ai_data_query(): void
    {
        $roleStoreOwner = Role::firstOrCreate(['name' => 'store_owner'], ['display_name' => 'Store Owner', 'category' => 'store']);
        $roleCourier = Role::firstOrCreate(['name' => 'courier'], ['display_name' => 'Courier', 'category' => 'courier']);

        $owner = User::factory()->create(['role' => User::ROLE_STORE_ADMIN]);
        $owner->roles()->attach($roleStoreOwner->id);

        $pendingStore = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'submitted',
            'is_active' => false,
        ]);

        $courierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'name' => 'Rahasia Pelamar']);
        $courier = Courier::create([
            'user_id' => $courierUser->id,
            'is_verified' => true,
            'is_available' => true,
            'store_id' => null,
            'vehicle_type' => 'Motor',
        ]);

        /** @var AiDataQueryService $dataQueryService */
        $dataQueryService = app(AiDataQueryService::class);
        $reply = $dataQueryService->getStoreApplicantsResponse($owner);

        $this->assertStringContainsString('Toko Anda belum berstatus aktif dan disetujui', $reply);
        $this->assertStringNotContainsString('Rahasia Pelamar', $reply);

        // When store is approved and active
        $pendingStore->update(['status' => 'approved', 'is_active' => true]);
        $approvedReply = $dataQueryService->getStoreApplicantsResponse($owner);
        $this->assertStringContainsString('Rahasia Pelamar', $approvedReply);
    }

    public function test_guest_ai_rate_limiter_blocks_excessive_requests(): void
    {
        /** @var AiAssistantService $service */
        $service = app(AiAssistantService::class);

        // Within limit
        $res = $service->replyForGuest('Halo siapa kamu?', '192.168.1.100');
        $this->assertNotEmpty($res['answer']);

        // Set cache key to exceed 30 per hour
        $guestKey = 'ai_guest_rate:' . md5('192.168.1.100') . ':' . now()->format('YmdH');
        Cache::put($guestKey, 35, now()->addHour());

        $this->expectException(AiRateLimitException::class);
        $service->replyForGuest('Halo siapa kamu?', '192.168.1.100');
    }

    public function test_guest_ask_http_endpoint_has_throttle_middleware(): void
    {
        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Halo apa saja layanan laundry?',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.answer'));
    }

    public function test_sync_user_roles_does_not_escalate_arbitrary_admin_substring_emails(): void
    {
        $roleCustomer = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer', 'category' => 'customer']);

        $attacker = User::factory()->create([
            'email' => 'fake_admin_attacker@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $attacker->roles()->attach($roleCustomer->id);

        $this->artisan('auth:sync-roles')->assertSuccessful();

        $attacker->refresh();
        $this->assertEquals(User::ROLE_CUSTOMER, $attacker->role);
        $this->assertNotEquals(User::ROLE_SUPER_ADMIN, $attacker->role);
    }
}
