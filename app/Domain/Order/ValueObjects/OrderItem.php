<?php

declare(strict_types=1);

namespace App\Domain\Order\ValueObjects;

class OrderItem
{
    public function __construct(
        private int $productId,
        private int $quantity,
        private string $unitPrice,
    ) {}

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getLineTotal(): string
    {
        return bcmul($this->unitPrice, (string) $this->quantity, 2);
    }
}
