<?php

declare(strict_types=1);

namespace App\Domain\Order\Contracts;

use App\Domain\Order\Entities\Order;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findByIdempotencyKey(string $key): ?Order;

    public function save(Order $order): Order;

    public function updateStatus(Order $order): void;

    /**
     * @return Order[]
     */
    public function findByUserId(int $userId, ?string $status = null, int $page = 1, int $perPage = 15): array;

    public function countByUserId(int $userId, ?string $status = null): int;
}
