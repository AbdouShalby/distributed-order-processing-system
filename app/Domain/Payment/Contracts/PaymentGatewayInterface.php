<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\ValueObjects\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Simulate a payment for an order.
     * Returns a PaymentResult indicating success or failure.
     */
    public function charge(int $orderId, string $amount): PaymentResult;
}
