<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_check_endpoint_returns_success_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                    'service' => 'LaundryKu API',
                    'database' => 'connected',
                ],
            ]);
    }
}
