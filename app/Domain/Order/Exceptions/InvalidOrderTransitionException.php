<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use App\Domain\Order\Enums\OrderStatus;

class InvalidOrderTransitionException extends \DomainException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(
            "Invalid order status transition: {$from->value} → {$to->value}"
        );
    }
}
