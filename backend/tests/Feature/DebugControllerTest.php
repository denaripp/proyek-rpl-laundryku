<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.fonnte.api_token' => 'test_token_debug_123']);
        config(['services.fonnte.base_url' => 'https://api.fonnte.com']);
    }

    public function test_debug_fonnte_endpoint(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'target' => ['628315068475'],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/debug/fonnte', [
            'phone' => '08315068475',
            'code' => '123456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Fonnte OTP sent successfully');
    }

    public function test_debug_otp_endpoint(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/debug/otp', [
            'phone' => '08315068475',
            'channel' => 'whatsapp',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'OTP generated and sent');
        $this->assertNotNull($response->json('otp_id'));
    }
}
