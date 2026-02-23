<?php

declare(strict_types=1);

namespace App\Application\DTOs;

class CreateOrderDTO
{
    /**
     * @param  array<array{product_id: int, quantity: int}>  $items
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly string $idempotencyKey,
    ) {}
}
