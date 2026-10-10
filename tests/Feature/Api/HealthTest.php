<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_the_success_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJsonStructure([
                'success',
                'message',
                'data' => ['status', 'version', 'time'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'API is up',
                'data' => ['status' => 'ok', 'version' => 'v1'],
            ]);
    }

    public function test_health_endpoint_is_not_served_without_the_version_prefix(): void
    {
        $this->getJson('/api/health')->assertNotFound();
    }
}
