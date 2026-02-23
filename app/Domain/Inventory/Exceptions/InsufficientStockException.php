<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

class InsufficientStockException extends \DomainException
{
    public function __construct(int $productId, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient stock for product {$productId}. Requested: {$requested}, Available: {$available}"
        );
    }

    public static function forProduct(int $productId, int $requested, int $available): self
    {
        return new self($productId, $requested, $available);
    }
}
