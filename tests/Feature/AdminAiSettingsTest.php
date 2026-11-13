<?php

namespace Tests\Feature;

use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->first();
        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'name' => 'Platform Super Admin',
            'email' => 'admin@laundryku.test',
        ]);
        if ($superAdminRole) {
            $this->superAdmin->roles()->attach($superAdminRole->id);
        }

        $customerRole = Role::where('name', 'customer')->first();
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Budi Customer',
            'email' => 'budi@gmail.test',
        ]);
        if ($customerRole) {
            $this->customer->roles()->attach($customerRole->id);
        }
    }

    public function test_ai_service_uses_dynamic_model_from_setting_with_fallback_to_config(): void
    {
        $service = app(AiAssistantService::class);

        // 1. Without setting, fallback to config
        Setting::forget('AI_MODEL');
        $this->assertSame(config('ai.model', 'liquid/lfm-2.5-2.6b:free'), $service->getActiveModel());

        // 2. When setting is configured, use setting
        Setting::set('AI_MODEL', 'google/gemini-2.5-flash', 'string', 'ai');
        $this->assertSame('google/gemini-2.5-flash', $service->getActiveModel());

        // 3. Lowercase key lookup also works
        $this->assertSame('google/gemini-2.5-flash', Setting::get('ai_model'));
    }

    public function test_ai_service_uses_dynamic_temperature_and_max_rounds(): void
    {
        $service = app(AiAssistantService::class);

        Setting::set('AI_TEMPERATURE', 0.7, 'float', 'ai');
        Setting::set('AI_MAX_TOOL_ROUNDS', 8, 'integer', 'ai');

        $this->assertSame(0.7, $service->getTemperature());
        $this->assertSame(8, $service->getMaxToolRounds());
    }

    public function test_ai_service_can_test_model_connection(): void
    {
        $service = app(AiAssistantService::class);

        $res = $service->testModelConnection('google/gemini-2.5-flash');

        $this->assertIsArray($res);
        $this->assertArrayHasKey('success', $res);
        $this->assertArrayHasKey('latency_ms', $res);
        $this->assertArrayHasKey('message', $res);
        $this->assertSame('google/gemini-2.5-flash', $res['model']);
    }

    public function test_non_admin_cannot_access_ai_settings_endpoint(): void
    {
        // Unauthenticated -> 401
        $resUnauth = $this->getJson('/api/v1/admin/ai-settings');
        $resUnauth->assertStatus(401);

        // Customer -> 403 Forbidden
        Sanctum::actingAs($this->customer, ['role:customer']);
        $resCust = $this->getJson('/api/v1/admin/ai-settings');
        $resCust->assertStatus(403);

        $resCustPut = $this->putJson('/api/v1/admin/ai-settings', ['model' => 'google/gemini-2.5-flash']);
        $resCustPut->assertStatus(403);
    }

    public function test_super_admin_can_get_ai_settings(): void
    {
        Setting::set('AI_MODEL', 'google/gemini-2.5-flash', 'string', 'ai');
        Setting::set('AI_TEMPERATURE', 0.3, 'float', 'ai');
        Setting::set('AI_MAX_TOOL_ROUNDS', 5, 'integer', 'ai');

        Sanctum::actingAs($this->superAdmin, ['role:super_admin']);

        $res = $this->getJson('/api/v1/admin/ai-settings');

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.active_model', 'google/gemini-2.5-flash');
        $res->assertJsonPath('data.temperature', 0.3);
        $res->assertJsonPath('data.max_tool_rounds', 5);
        $res->assertJsonStructure([
            'success',
            'data' => [
                'active_model',
                'temperature',
                'max_tool_rounds',
                'is_custom_model',
                'presets' => [
                    '*' => [
                        'id',
                        'name',
                        'provider',
                        'category',
                        'badge',
                        'description',
                        'cost_estimate',
                        'is_free',
                    ],
                ],
                'provider',
                'api_key_configured',
            ],
        ]);

        $presets = $res->json('data.presets');
        $this->assertCount(8, $presets);

        $freePresets = array_filter($presets, fn ($p) => $p['category'] === 'free');
        $paidPresets = array_filter($presets, fn ($p) => $p['category'] === 'paid');

        $this->assertCount(4, $freePresets);
        $this->assertCount(4, $paidPresets);

        foreach ($freePresets as $preset) {
            $this->assertTrue($preset['is_free']);
            $this->assertStringContainsString('Free', $preset['cost_estimate']);
        }

        foreach ($paidPresets as $preset) {
            $this->assertFalse($preset['is_free']);
            $this->assertNotEmpty($preset['cost_estimate']);
        }
    }

    public function test_super_admin_can_update_ai_settings_and_audit_log_is_recorded(): void
    {
        Setting::set('AI_MODEL', 'liquid/lfm-2.5-2.6b:free', 'string', 'ai');

        Sanctum::actingAs($this->superAdmin, ['role:super_admin']);

        $payload = [
            'model' => 'meta-llama/llama-3.3-70b-instruct:free',
            'temperature' => 0.5,
            'max_tool_rounds' => 7,
        ];

        $res = $this->putJson('/api/v1/admin/ai-settings', $payload);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.active_model', 'meta-llama/llama-3.3-70b-instruct:free');
        $res->assertJsonPath('data.temperature', 0.5);
        $res->assertJsonPath('data.max_tool_rounds', 7);

        // Verify database state
        $this->assertSame('meta-llama/llama-3.3-70b-instruct:free', Setting::get('AI_MODEL'));
        $this->assertSame(0.5, (float) Setting::get('AI_TEMPERATURE'));
        $this->assertSame(7, (int) Setting::get('AI_MAX_TOOL_ROUNDS'));

        // Verify audit log
        $auditLog = AuditLog::where('action', 'UPDATE_AI_MODEL')
            ->where('resource_type', 'setting')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->superAdmin->id, $auditLog->actor_id);
        $this->assertStringContainsString('meta-llama/llama-3.3-70b-instruct:free', (string) $auditLog->after_value);
    }

    public function test_update_ai_settings_validates_input(): void
    {
        Sanctum::actingAs($this->superAdmin, ['role:super_admin']);

        // Empty model -> 422
        $res = $this->putJson('/api/v1/admin/ai-settings', [
            'model' => '',
        ]);
        $res->assertStatus(422);

        // Invalid temperature (> 1) -> 422
        $resTemp = $this->putJson('/api/v1/admin/ai-settings', [
            'model' => 'google/gemini-2.5-flash',
            'temperature' => 2.5,
        ]);
        $resTemp->assertStatus(422);
    }

    public function test_super_admin_can_call_test_connection_endpoint(): void
    {
        Sanctum::actingAs($this->superAdmin, ['role:super_admin']);

        $res = $this->postJson('/api/v1/admin/ai-settings/test', [
            'model' => 'google/gemini-2.5-flash',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.model', 'google/gemini-2.5-flash');
    }

    public function test_get_ai_settings_includes_live_free_models_availability_and_recent_usage(): void
    {
        Cache::put('openrouter:models_catalog_v1', [
            'liquid/lfm-2.5-2.6b:free' => [
                'id' => 'liquid/lfm-2.5-2.6b:free',
                'name' => 'Liquid LFM 2.5 2.6B (free)',
                'is_free' => true,
                'supports_tools' => true,
                'context_length' => 65536,
            ],
            'openrouter/free' => [
                'id' => 'openrouter/free',
                'name' => 'Free Models Router',
                'is_free' => true,
                'supports_tools' => true,
                'context_length' => 200000,
            ],
        ], 3600);

        AiUsageLog::create([
            'user_id' => $this->customer->id,
            'model' => 'google/gemini-2.0-flash-exp:free',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0,
            'tool_rounds' => 0,
            'status' => 'provider_error',
            'flags' => ['provider_error' => 'AI provider error (HTTP 404): no provider serves this model anymore'],
        ]);

        AiUsageLog::create([
            'user_id' => $this->customer->id,
            'model' => 'openrouter/free',
            'prompt_tokens' => 120,
            'completion_tokens' => 45,
            'total_tokens' => 165,
            'estimated_cost_usd' => 0,
            'tool_rounds' => 0,
            'status' => 'success',
            'flags' => ['resolved_model' => 'google/gemma-4-31b-it:free'],
        ]);

        Sanctum::actingAs($this->superAdmin, ['role:super_admin']);

        $res = $this->getJson('/api/v1/admin/ai-settings');
        $res->assertStatus(200);

        $presets = collect($res->json('data.presets'))->keyBy('id');
        $this->assertCount(8, $presets);
        $this->assertTrue($presets['liquid/lfm-2.5-2.6b:free']['available_on_openrouter']);
        $this->assertFalse($presets['google/gemini-2.0-flash-exp:free']['available_on_openrouter']);

        // live_free_models excludes presets already in catalog list
        $liveIds = array_column($res->json('data.live_free_models'), 'id');
        $this->assertContains('openrouter/free', $liveIds);
        $this->assertNotContains('liquid/lfm-2.5-2.6b:free', $liveIds);

        $recent = $res->json('data.recent_usage');
        $this->assertCount(2, $recent);
        $this->assertSame('openrouter/free', $recent[0]['model']);
        $this->assertSame('google/gemma-4-31b-it:free', $recent[0]['resolved_model']);
        $this->assertSame('success', $recent[0]['status']);
        $this->assertSame('provider_error', $recent[1]['status']);
        $this->assertStringContainsString('HTTP 404', (string) $recent[1]['error']);
    }

    public function test_openrouter_client_records_resolved_model_and_provider_error_in_usage_logs(): void
    {
        config([
            'ai.api_key' => 'test-openrouter-key',
            'ai.base_url' => 'https://openrouter.ai/api/v1',
        ]);
        Setting::set('AI_MODEL', 'openrouter/free', 'string', 'ai');

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'model' => 'google/gemma-4-31b-it:free',
                    'choices' => [['message' => ['content' => 'Paket cuci kiloan mulai Rp 6.000/kg.']]],
                    'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 20],
                ], 200)
                ->push([
                    'error' => ['message' => 'no provider serves this model anymore'],
                ], 404),
        ]);

        $assistant = app(AiAssistantService::class);
        $assistant->chat($this->customer, null, 'Bagaimana estimasi harga layanan laundry kiloan?');

        $okLog = AiUsageLog::latest('id')->first();
        $this->assertSame('openrouter/free', $okLog->model);
        $this->assertSame('success', $okLog->status);
        $this->assertSame('google/gemma-4-31b-it:free', $okLog->flags['resolved_model'] ?? null);

        $okMsg = AiMessage::where('role', 'assistant')->latest('id')->first();
        $this->assertSame('google/gemma-4-31b-it:free', $okMsg->metadata['resolved_model'] ?? null);

        Setting::set('AI_MODEL', 'google/gemini-2.0-flash-exp:free', 'string', 'ai');
        $assistant->chat($this->customer, null, 'Bagaimana estimasi harga layanan laundry ekspres?');

        $errLog = AiUsageLog::latest('id')->first();
        $this->assertSame('google/gemini-2.0-flash-exp:free', $errLog->model);
        $this->assertSame('provider_error', $errLog->status);
        $this->assertStringContainsString('HTTP 404', (string) ($errLog->flags['provider_error'] ?? ''));
    }
}
