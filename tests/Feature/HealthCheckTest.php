<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_public_and_reports_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
    }
}
