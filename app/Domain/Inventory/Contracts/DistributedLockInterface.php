<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Contracts;

interface DistributedLockInterface
{
    /**
     * Acquire locks for multiple product IDs (sorted ascending to prevent deadlocks).
     * Uses jittered exponential backoff retry strategy.
     *
     * @param int[] $productIds
     * @return bool True if all locks acquired
     */
    public function acquireForProducts(array $productIds, int $ttlSeconds = 10): bool;

    /**
     * Release all currently held locks.
     */
    public function releaseAll(): void;
}
