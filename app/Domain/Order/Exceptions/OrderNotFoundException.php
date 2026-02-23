<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

class OrderNotFoundException extends \DomainException
{
    public function __construct(int $orderId)
    {
        parent::__construct("Order not found: {$orderId}");
    }
}
