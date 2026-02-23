<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function health_endpoint_returns_ok(): void
    {
        // Mock Redis for testing (no actual Redis in test env)
        Redis::shouldReceive('ping')->once()->andReturn('PONG');

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'services' => ['database', 'redis'],
                'timestamp',
            ])
            ->assertJsonPath('status', 'ok');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function health_endpoint_shows_degraded_when_redis_down(): void
    {
        Redis::shouldReceive('ping')->once()->andThrow(new \Exception('Connection refused'));

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.database', 'connected')
            ->assertJsonPath('services.redis', 'disconnected');
    }
}
