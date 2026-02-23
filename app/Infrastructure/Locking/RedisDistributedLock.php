<?php

declare(strict_types=1);

namespace App\Infrastructure\Locking;

use App\Domain\Inventory\Contracts\DistributedLockInterface;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Redis distributed lock with:
 * - Per-product lock granularity
 * - Unique token per lock (prevents releasing other's locks)
 * - Lua script for safe atomic release
 * - Ascending product_id ordering (prevents deadlocks)
 * - Jittered exponential backoff retry strategy
 */
class RedisDistributedLock implements DistributedLockInterface
{
    private const LOCK_PREFIX = 'inventory:product:';
    private const MAX_RETRIES = 5;
    private const BASE_DELAY_MS = 100;
    private const JITTER_FACTOR = 0.25;

    /**
     * Lua script for safe release: only delete if token matches.
     */
    private const RELEASE_SCRIPT = <<<'LUA'
        if redis.call("get", KEYS[1]) == ARGV[1] then
            return redis.call("del", KEYS[1])
        else
            return 0
        end
    LUA;

    /** @var array<string, string> key => token */
    private array $acquiredLocks = [];

    public function acquireForProducts(array $productIds, int $ttlSeconds = 10): bool
    {
        // Sort ascending to prevent deadlocks
        $sortedIds = $productIds;
        sort($sortedIds);
        $sortedIds = array_unique($sortedIds);

        $this->acquiredLocks = [];

        foreach ($sortedIds as $productId) {
            $acquired = $this->acquireWithRetry(
                self::LOCK_PREFIX . $productId,
                $ttlSeconds
            );

            if (! $acquired) {
                // Failed to acquire — release all previously acquired locks
                $this->releaseAll();

                return false;
            }
        }

        return true;
    }

    public function releaseAll(): void
    {
        // Release in reverse order
        foreach (array_reverse($this->acquiredLocks, true) as $key => $token) {
            $this->releaseLock($key, $token);
        }

        $this->acquiredLocks = [];
    }

    // ─── Private ──────────────────────────────────────

    private function acquireWithRetry(string $key, int $ttlSeconds): bool
    {
        $token = Str::uuid()->toString();

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                $this->sleepWithJitter($attempt);
            }

            $result = Redis::set($key, $token, 'EX', $ttlSeconds, 'NX');

            if ($result) {
                $this->acquiredLocks[$key] = $token;

                return true;
            }
        }

        return false;
    }

    private function releaseLock(string $key, string $token): void
    {
        Redis::eval(self::RELEASE_SCRIPT, 1, $key, $token);
    }

    /**
     * Jittered exponential backoff.
     * Delay = baseDelay * 2^(attempt-1) * (1 ± jitter)
     */
    private function sleepWithJitter(int $attempt): void
    {
        $delay = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
        $jitter = $delay * self::JITTER_FACTOR;
        $actualDelay = $delay + random_int((int) -$jitter, (int) $jitter);
        $actualDelay = max(10, $actualDelay); // minimum 10ms

        usleep($actualDelay * 1000);
    }
}
