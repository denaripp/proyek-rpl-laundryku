<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FontneOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.fonnte.api_token' => 'test_fonnte_token_12345']);
        config(['services.fonnte.base_url' => 'https://api.fonnte.com']);
        Role::firstOrCreate(['code' => 'customer'], ['name' => 'Customer']);
    }

    public function test_fonnte_send_otp_with_valid_phone(): void
    {
        // Mock official Fonnte API endpoint POST https://api.fonnte.com/send
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'target' => ['628315068475'],
                'id' => ['msg_123456'],
                'detail' => 'success',
            ], 200),
        ]);

        $fonnte = new FontneService();
        $result = $fonnte->sendOtp('628315068475', '123456');

        $this->assertTrue($result['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send' &&
                   $request['target'] === '628315068475' &&
                   $request->hasHeader('Authorization', 'test_fonnte_token_12345');
        });
    }

    public function test_fonnte_normalize_phone_08_to_62(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $fonnte = new FontneService();
        // Should convert 08315068475 → 628315068475
        $fonnte->sendOtp('08315068475', '123456');

        Http::assertSent(function ($request) {
            return $request['target'] === '628315068475';
        });
    }

    public function test_fonnte_detects_http_200_with_failure_body(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => false,
                'reason' => 'invalid token',
            ], 200),
        ]);

        $fonnte = new FontneService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Fonnte delivery error: invalid token');

        $fonnte->sendOtp('08315068475', '123456');
    }

    public function test_fonnte_detects_http_500_failure(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $fonnte = new FontneService();

        $this->expectException(\Exception::class);
        $fonnte->sendOtp('08315068475', '123456');
    }

    public function test_registration_with_whatsapp_otp_flow(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Muhammad Najwa Syarif',
            'phone_number' => '08315068475',
            'channel' => 'whatsapp',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.destination', '628315068475');
    }
}
