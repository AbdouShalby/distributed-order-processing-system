<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Contracts;

interface ProductRepositoryInterface
{
    /**
     * Find product by ID with a pessimistic lock (SELECT FOR UPDATE).
     */
    public function findByIdForUpdate(int $id): ?array;

    /**
     * Find product by ID (read-only).
     */
    public function findById(int $id): ?array;

    /**
     * Decrement stock atomically. Must be called inside a DB transaction.
     */
    public function decrementStock(int $productId, int $quantity): void;

    /**
     * Restore stock (for cancellation). Must be called inside a DB transaction.
     */
    public function incrementStock(int $productId, int $quantity): void;

    /**
     * Get all products.
     *
     * @return array[]
     */
    public function findAll(): array;
}
