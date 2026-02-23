<?php

declare(strict_types=1);

namespace App\Infrastructure\Locking;

use App\Domain\Inventory\Contracts\DistributedLockInterface;

/**
 * In-memory distributed lock for testing.
 * Uses a static array to simulate lock behavior within a single process.
 */
class InMemoryDistributedLock implements DistributedLockInterface
{
    /** @var array<string, bool> */
    private static array $locks = [];

    /** @var string[] Keys acquired by this instance */
    private array $acquiredKeys = [];

    public function acquireForProducts(array $productIds, int $ttlSeconds = 10): bool
    {
        $sortedIds = $productIds;
        sort($sortedIds);
        $sortedIds = array_unique($sortedIds);

        $this->acquiredKeys = [];

        foreach ($sortedIds as $productId) {
            $key = "inventory:product:{$productId}";

            if (isset(self::$locks[$key])) {
                // Lock already held — release what we acquired and fail
                $this->releaseAll();

                return false;
            }

            self::$locks[$key] = true;
            $this->acquiredKeys[] = $key;
        }

        return true;
    }

    public function releaseAll(): void
    {
        foreach ($this->acquiredKeys as $key) {
            unset(self::$locks[$key]);
        }

        $this->acquiredKeys = [];
    }

    /**
     * Reset all locks (useful between tests).
     */
    public static function reset(): void
    {
        self::$locks = [];
    }
}
