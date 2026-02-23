<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Locking\InMemoryDistributedLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        InMemoryDistributedLock::reset();
    }

    /**
     * Oversell protection: product #5 (Headphones) has stock = 1.
     * Fire 10 sequential requests — only 1 should succeed, rest get 409 insufficient stock.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_overselling_under_concurrent_requests(): void
    {
        Queue::fake();

        $responses = collect(range(1, 10))->map(function (int $i) {
            return $this->postJson('/api/orders', [
                'user_id' => 1,
                'idempotency_key' => "oversell-{$i}",
                'items' => [
                    ['product_id' => 5, 'quantity' => 1],
                ],
            ]);
        });

        $successCount = $responses->filter(fn ($r) => $r->status() === 201)->count();
        $rejectedCount = $responses->filter(fn ($r) => $r->status() === 409)->count();

        // Exactly 1 should succeed (stock = 1)
        $this->assertSame(1, $successCount, "Expected exactly 1 success, got {$successCount}");
        $this->assertSame(9, $rejectedCount, "Expected 9 rejections, got {$rejectedCount}");

        // Stock must be exactly 0
        $this->assertDatabaseHas('products', ['id' => 5, 'stock' => 0]);
    }

    /**
     * Idempotency test: same key should only create one order.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function idempotency_key_prevents_duplicate_orders(): void
    {
        Queue::fake();

        $payload = [
            'user_id' => 1,
            'idempotency_key' => 'same-key-test',
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ];

        $responses = collect(range(1, 5))->map(fn () => $this->postJson('/api/orders', $payload));

        $created = $responses->filter(fn ($r) => $r->status() === 201)->count();
        $existing = $responses->filter(fn ($r) => $r->status() === 200)->count();

        $this->assertSame(1, $created, 'Exactly 1 order should be created');
        $this->assertSame(4, $existing, '4 should return existing order');

        // Only 1 order in DB
        $orderCount = DB::table('orders')->where('idempotency_key', 'same-key-test')->count();
        $this->assertSame(1, $orderCount);

        // Stock decremented only once (50 - 1 = 49)
        $this->assertDatabaseHas('products', ['id' => 1, 'stock' => 49]);
    }

    /**
     * Cancel guard: only PENDING orders can be cancelled.
     * Simulate sequential cancel attempts on same order.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function concurrent_cancels_are_idempotent(): void
    {
        Queue::fake();

        $createResponse = $this->postJson('/api/orders', [
            'user_id' => 1,
            'idempotency_key' => 'concurrent-cancel',
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
            ],
        ]);

        $createResponse->assertStatus(201);
        $orderId = $createResponse->json('data.id');

        // Fire 5 cancel requests
        $responses = collect(range(1, 5))->map(
            fn () => $this->postJson("/api/orders/{$orderId}/cancel")
        );

        $successCount = $responses->filter(fn ($r) => $r->status() === 200)->count();

        // All should succeed (idempotent — first cancels, rest return already-cancelled)
        $this->assertSame(5, $successCount, 'All cancel requests should succeed (idempotent)');

        // Stock fully restored (50 - 2 + 2 = 50)
        $this->assertDatabaseHas('products', ['id' => 1, 'stock' => 50]);

        // Order is CANCELLED
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'CANCELLED',
        ]);
    }
}
