<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

class OrderNotCancellableException extends \DomainException
{
    public function __construct(string $currentStatus)
    {
        parent::__construct(
            "Order cannot be cancelled. Current status: {$currentStatus}"
        );
    }
}
