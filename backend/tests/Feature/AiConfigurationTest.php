<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AiConfigurationTest extends TestCase
{
    /**
     * Test 1: Mock is active when APP_ENV=local and AI_MOCK_IN_LOCAL=true.
     */
    public function test_mock_is_active_when_app_env_is_local_and_mock_flag_is_true(): void
    {
        app()['env'] = 'local';
        config([
            'ai.mock_in_local' => true,
            'ai.openai.api_key' => '',
            'ai.rate_limit_per_minute' => 50,
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Bagaimana cara memesan laundry?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_fallback', true)
            ->assertJsonPath('data.provider', 'local_fallback');

        // Confirms mock logic generated simulated answer
        $this->assertStringContainsString('Cari Toko', $response->json('data.answer'));
        $this->assertStringNotContainsString('Layanan bantuan otomatis sedang tidak tersedia', $response->json('data.answer'));
    }

    /**
     * Test 2: Mock is NOT active when APP_ENV=production, even if AI_MOCK_IN_LOCAL=true.
     */
    public function test_mock_is_inactive_when_app_env_is_production_even_if_mock_flag_is_true(): void
    {
        app()['env'] = 'production';
        config([
            'ai.mock_in_local' => true,
            'ai.openai.api_key' => '',
            'ai.rate_limit_per_minute' => 50,
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Bagaimana cara memesan laundry?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_fallback', true);

        // Confirms mock is turned off and neutral fallback is returned instead
        $this->assertEquals(
            'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.',
            $response->json('data.answer')
        );
    }

    /**
     * Test 3: When mock_in_local=false and OPENAI_API_KEY is empty, returns neutral fallback (not 500).
     */
    public function test_empty_api_key_without_mock_returns_neutral_fallback_not_500(): void
    {
        app()['env'] = 'local';
        config([
            'ai.mock_in_local' => false,
            'ai.openai.api_key' => '',
            'ai.rate_limit_per_minute' => 50,
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Metode pembayaran apa saja?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_fallback', true);

        $this->assertEquals(
            'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.',
            $response->json('data.answer')
        );
    }

    /**
     * Test 4: Rate limit is read from config (AI_RATE_LIMIT / ai.rate_limit_per_minute).
     */
    public function test_rate_limit_is_read_from_config(): void
    {
        app()['env'] = 'local';
        config([
            'ai.mock_in_local' => true,
            'ai.openai.api_key' => '',
            'ai.rate_limit_per_minute' => 2,
        ]);

        RateLimiter::clear('127.0.0.1');

        // Request 1: allowed
        $res1 = $this->postJson('/api/v1/chatbot/guest/ask', ['message' => 'Halo 1']);
        $res1->assertStatus(200);

        // Request 2: allowed
        $res2 = $this->postJson('/api/v1/chatbot/guest/ask', ['message' => 'Halo 2']);
        $res2->assertStatus(200);

        // Request 3: throttled at 429
        $res3 = $this->postJson('/api/v1/chatbot/guest/ask', ['message' => 'Halo 3']);
        $res3->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    /**
     * Test 5: Successful remote API call via Http::fake.
     */
    public function test_remote_ai_successful_call(): void
    {
        Http::fake([
            '*chat/completions*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Halo! Ini respons langsung dari OpenRouter.',
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 25],
            ], 200),
        ]);

        config([
            'ai.mock_in_local' => false,
            'ai.openai.api_key' => 'sk-test-sample-secret-key-12345',
            'ai.openai.model' => 'google/gemma-4-26b-a4b-it:free',
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Tes koneksi AI',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.answer', 'Halo! Ini respons langsung dari OpenRouter.')
            ->assertJsonPath('data.is_fallback', false);
    }

    /**
     * Test 6: Remote 401 returns neutral fallback without 500 and key does not appear in logs.
     */
    public function test_remote_ai_401_returns_neutral_fallback_and_sanitizes_logs(): void
    {
        $secretKey = 'sk-or-v1-secret-never-leak-9988';

        Http::fake([
            '*chat/completions*' => Http::response([
                'error' => ['message' => 'User not found.', 'code' => 401],
            ], 401),
        ]);

        config([
            'ai.mock_in_local' => false,
            'ai.openai.api_key' => $secretKey,
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Halo AI',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.answer', 'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.')
            ->assertJsonPath('data.is_fallback', true);

        // Verify key is not written in log
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $recentLog = file_get_contents($logPath);
            $this->assertStringNotContainsString($secretKey, $recentLog);
        }
    }

    /**
     * Test 7: When primary model hits 429, falls back to secondary model and succeeds.
     */
    public function test_remote_ai_429_falls_back_to_secondary_model(): void
    {
        $primaryModel = 'google/gemma-4-26b-a4b-it:free';
        $secondaryModel = 'liquid/lfm-2.5-2.6b:free';

        Http::fake([
            '*chat/completions*' => function (\Illuminate\Http\Client\Request $request) use ($primaryModel, $secondaryModel) {
                $payload = json_decode($request->body(), true);
                if (($payload['model'] ?? '') === $primaryModel) {
                    return Http::response(['error' => ['message' => 'Rate limit exceeded']], 429);
                }

                if (($payload['model'] ?? '') === $secondaryModel) {
                    return Http::response([
                        'choices' => [
                            ['message' => ['content' => 'Respons sukses dari model fallback!']],
                        ],
                        'usage' => ['total_tokens' => 30],
                    ], 200);
                }

                return Http::response(['error' => 'Unknown model'], 400);
            },
        ]);

        config([
            'ai.mock_in_local' => false,
            'ai.openai.api_key' => 'sk-test-valid-key',
            'ai.openai.model' => $primaryModel,
            'ai.openai.fallback_models' => [$secondaryModel],
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Halo AI dengan fallback',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.answer', 'Respons sukses dari model fallback!')
            ->assertJsonPath('data.is_fallback', false);
    }

    /**
     * Test 8: Remote connection timeout returns neutral fallback (not 500).
     */
    public function test_remote_ai_timeout_returns_neutral_fallback_not_500(): void
    {
        Http::fake([
            '*chat/completions*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        config([
            'ai.mock_in_local' => false,
            'ai.openai.api_key' => 'sk-test-timeout-key',
            'ai.openai.timeout' => 1,
        ]);

        $response = $this->postJson('/api/v1/chatbot/guest/ask', [
            'message' => 'Tes timeout',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.answer', 'Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS.')
            ->assertJsonPath('data.is_fallback', true);
    }
}
