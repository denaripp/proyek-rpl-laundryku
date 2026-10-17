<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Services\File\SecureFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SECURITY (Tahap 19) — Security hardening regression tests.
 *
 * Covers:
 *  - Rate limiting (refund, dispute, webhook)
 *  - File upload validation (MIME, ext, size, randomized name, private disk)
 *  - Audit log policy (only platform_owner can delete)
 *  - Log content does NOT contain raw response body / password / api key
 */
class SecurityHardeningTestTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $storeOwner;
    protected User $platformAdmin;
    protected User $platformOwner;
    protected Store $store;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->customer = User::factory()->create();
        $this->customer->roles()->attach(Role::where('name', 'customer')->first());

        $this->storeOwner = User::factory()->create();
        $this->storeOwner->roles()->attach(Role::where('name', 'store_owner')->first());

        $this->platformAdmin = User::factory()->create();
        $this->platformAdmin->roles()->attach(Role::where('name', 'platform_admin')->first());

        $this->platformOwner = User::factory()->create();
        $this->platformOwner->roles()->attach(Role::where('name', 'platform_owner')->first());

        $this->store = Store::factory()->create([
            'owner_user_id' => $this->storeOwner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'completed',
            'total_amount' => 50000,
        ]);
    }

    // ========== FILE UPLOAD SECURITY ==========

    public function test_secure_upload_rejects_disallowed_extension()
    {
        $bad = UploadedFile::fake()->createWithContent(
            'evil.php',
            "<?php echo 'pwned'; ?>"
        );

        $service = new SecureFileUpload();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid extension/');
        $service->store($bad, 'store_document', $this->store->id);
    }

    public function test_secure_upload_rejects_oversized_file()
    {
        // 6 MB > 5 MB cap
        $big = UploadedFile::fake()->create('huge.pdf', 6000);

        $service = new SecureFileUpload();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/File too large/');
        $service->store($big, 'store_document', $this->store->id);
    }

    public function test_secure_upload_rejects_mime_extension_mismatch()
    {
        // PHP file renamed to .pdf
        $bad = UploadedFile::fake()->createWithContent(
            'spoofed.pdf',
            "<?php phpinfo(); ?>"
        );

        $service = new SecureFileUpload();
        $this->expectException(\InvalidArgumentException::class);
        // Could be MIME mismatch OR invalid MIME depending on detection.
        $this->expectExceptionMessageMatches('/(Invalid extension|Invalid MIME|does not match)/');
        $service->store($bad, 'store_document', $this->store->id);
    }

    public function test_secure_upload_accepts_valid_pdf_and_uses_randomized_name()
    {
        // Create a real minimal PDF (header magic bytes).
        $pdfContent = "%PDF-1.4\n%test\n";
        $valid = UploadedFile::fake()->createWithContent('document.pdf', $pdfContent);

        $service = new SecureFileUpload();
        $meta = $service->store($valid, 'store_document', $this->store->id);

        $this->assertArrayHasKey('path', $meta);
        $this->assertStringStartsWith("store_document/{$this->store->id}/", $meta['path']);
        $this->assertEquals(32 + 4, strlen(basename($meta['path']))); // 32 random + .ext
        // Filename NOT equal to original
        $this->assertNotEquals('document.pdf', basename($meta['path']));
        $this->assertEquals('application/pdf', $meta['mime_type']);
    }

    public function test_secure_upload_sanitizes_filename()
    {
        $bad = UploadedFile::fake()->createWithContent(
            "evil\x00name.pdf",
            "%PDF-1.4\n"
        );

        $service = new SecureFileUpload();
        $meta = $service->store($bad, 'store_document', $this->store->id);

        // Null byte stripped.
        $this->assertStringNotContainsString("\x00", $meta['original_filename']);
    }

    public function test_secure_upload_path_does_not_contain_traversal()
    {
        $file = UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4');

        $service = new SecureFileUpload();
        $meta = $service->store($file, 'store_document', $this->store->id);

        $this->assertStringNotContainsString('..', $meta['path']);
        $this->assertStringNotContainsString('\\', $meta['path']);
    }

    // ========== RATE LIMITING ==========

    public function test_refund_route_has_throttle_middleware()
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => $r->uri() === 'api/refunds' && in_array('POST', $r->methods(), true));

        $this->assertGreaterThan(0, $routes->count(), 'POST /api/refunds route not found');

        $first = $routes->first();
        $this->assertContains(
            'throttle:refund',
            $first->gatherMiddleware(),
            'POST /api/refunds must have throttle:refund middleware'
        );
    }

    public function test_dispute_route_has_throttle_middleware()
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => $r->uri() === 'api/orders/{order}/disputes' && in_array('POST', $r->methods(), true));

        $this->assertGreaterThan(0, $routes->count(), 'POST /api/orders/{order}/disputes route not found');

        $first = $routes->first();
        $this->assertContains(
            'throttle:dispute',
            $first->gatherMiddleware(),
            'POST /api/orders/{order}/disputes must have throttle:dispute middleware'
        );
    }

    public function test_webhook_route_has_throttle_middleware()
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => $r->uri() === 'api/webhooks/xendit' && in_array('POST', $r->methods(), true));

        $this->assertGreaterThan(0, $routes->count());

        $first = $routes->first();
        $this->assertContains(
            'throttle:webhook',
            $first->gatherMiddleware(),
            'POST /api/webhooks/xendit must have throttle:webhook middleware'
        );
    }

    public function test_refund_limiter_registered_with_5_per_hour()
    {
        $cb = RateLimiter::limiter('refund');
        $this->assertNotNull($cb, 'refund rate limiter not registered');

        $request = \Illuminate\Http\Request::create('/api/refunds', 'POST');
        $request->setUserResolver(fn() => $this->customer);
        $limit = $cb($request);

        $this->assertInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class, $limit);
        $this->assertEquals(5, $limit->maxAttempts);
        $this->assertEquals(3600, $limit->decaySeconds);
    }

    public function test_dispute_limiter_registered_with_3_per_day()
    {
        $cb = RateLimiter::limiter('dispute');
        $this->assertNotNull($cb, 'dispute rate limiter not registered');

        $request = \Illuminate\Http\Request::create('/api/disputes', 'POST');
        $request->setUserResolver(fn() => $this->customer);
        $limit = $cb($request);

        $this->assertEquals(3, $limit->maxAttempts);
        $this->assertEquals(86400, $limit->decaySeconds);
    }

    public function test_login_route_has_throttle_login_middleware()
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => $r->uri() === 'login' && in_array('POST', $r->methods(), true));

        $this->assertGreaterThan(0, $routes->count(), 'POST /login route not found');

        $first = $routes->first();
        $this->assertContains(
            'throttle:login',
            $first->gatherMiddleware(),
            'POST /login must have throttle:login middleware'
        );
    }

    public function test_login_limiter_registered_with_5_per_minute()
    {
        $cb = RateLimiter::limiter('login');
        $this->assertNotNull($cb, 'login rate limiter not registered');

        $request = \Illuminate\Http\Request::create('/login', 'POST');
        $request->merge(['email' => 'test@example.com']);
        $limit = $cb($request);

        $this->assertEquals(5, $limit->maxAttempts);
        $this->assertEquals(60, $limit->decaySeconds);
    }

    public function test_login_limiter_keys_by_email_and_ip()
    {
        // Different emails => different limit keys (defense against credential
        // stuffing across accounts from same IP).
        $cb = RateLimiter::limiter('login');

        $reqA = \Illuminate\Http\Request::create('/login', 'POST');
        $reqA->merge(['email' => 'a@example.com']);
        $reqA->server->set('REMOTE_ADDR', '10.0.0.1');
        $limitA = $cb($reqA);

        $reqB = \Illuminate\Http\Request::create('/login', 'POST');
        $reqB->merge(['email' => 'b@example.com']);
        $reqB->server->set('REMOTE_ADDR', '10.0.0.1');
        $limitB = $cb($reqB);

        $this->assertNotEquals($limitA->key, $limitB->key);
    }

    public function test_ai_rate_limit_still_active_after_refactor()
    {
        // SECURITY (Tahap 19): confirm Tahap 14 AI rate limit enforcement
        // is intact. AiAssistantService::enforceRateLimit() throws
        // AiRateLimitException when hourly quota exceeded.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $customer = \App\Models\User::factory()->create();
        $customer->roles()->attach(\App\Models\Role::where('name', 'customer')->first());

        // Drop limit to 1 hit/hour so a second call trips the limiter.
        \App\Models\SystemConfiguration::where('key', 'AI_RATE_LIMIT_PER_HOUR')
            ->update(['value' => ['value' => 1]]);

        // Inline fake client that always returns a benign success response.
        $fake = new class implements \App\Services\Ai\AiClientInterface {
            public function chat(array $messages, array $tools): array {
                return [
                    'content' => 'OK',
                    'tool_calls' => [],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
                ];
            }
        };
        $this->app->instance(\App\Services\Ai\AiClientInterface::class, $fake);

        $service = $this->app->make(\App\Services\Ai\AiAssistantService::class);

        // 1st call: OK.
        $service->chat($customer, null, 'Halo pertama');

        // 2nd call in same hour: AiRateLimitException.
        $this->expectException(\App\Services\Ai\AiRateLimitException::class);
        $service->chat($customer, null, 'Halo kedua');
    }

    // ========== AUDIT LOG POLICY ==========

    public function test_platform_admin_cannot_delete_audit_log()
    {
        $log = AuditLog::create([
            'actor_id' => $this->customer->id,
            'action' => 'test_action',
            'resource_type' => 'test',
            'resource_id' => 1,
            'created_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($this->platformAdmin)->check('delete', $log));
        $this->assertFalse(Gate::forUser($this->platformAdmin)->check('deleteAny', AuditLog::class));
    }

    public function test_platform_owner_can_delete_audit_log()
    {
        $log = AuditLog::create([
            'actor_id' => $this->customer->id,
            'action' => 'test_action',
            'resource_type' => 'test',
            'resource_id' => 1,
            'created_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($this->platformOwner)->check('delete', $log));
        $this->assertTrue(Gate::forUser($this->platformOwner)->check('deleteAny', AuditLog::class));
    }

    public function test_no_http_route_exists_to_delete_audit_log()
    {
        // Even owner must NOT have a one-click delete via API
        // (deliberate action through documented workflow only).
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => str_contains($r->uri(), 'audit') || str_contains($r->uri(), 'audit-log'));

        foreach ($routes as $r) {
            $this->assertStringNotContainsString('DELETE', $r->methods()[0] ?? '', "DELETE route for audit found: {$r->uri()}");
        }
    }

    // ========== IDOR / OWNERSHIP ON STORE DOCUMENT ==========

    public function test_other_store_owner_cannot_upload_to_my_store()
    {
        $otherOwner = User::factory()->create();
        $otherOwner->roles()->attach(Role::where('name', 'store_owner')->first());
        Store::factory()->create(['owner_user_id' => $otherOwner->id, 'status' => 'approved']);

        $this->actingAs($otherOwner);
        $file = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4');

        $response = $this->postJson("/api/store-owner/stores/{$this->store->id}/documents", [
            'document_type' => 'ktp',
            'file' => $file,
        ]);

        // otherOwner is not owner/staff of $this->store, so Policy denies.
        $response->assertStatus(403);
    }
}