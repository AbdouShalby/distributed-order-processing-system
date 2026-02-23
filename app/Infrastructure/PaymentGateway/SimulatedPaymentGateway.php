<?php

declare(strict_types=1);

namespace App\Infrastructure\PaymentGateway;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\ValueObjects\PaymentResult;
use Illuminate\Support\Facades\Log;

/**
 * Simulated payment gateway.
 * 80% success rate, 20% failure rate.
 */
class SimulatedPaymentGateway implements PaymentGatewayInterface
{
    public function charge(int $orderId, string $amount): PaymentResult
    {
        // Simulate processing time (50-200ms)
        usleep(random_int(50_000, 200_000));

        // 80% success, 20% failure
        $isSuccess = random_int(1, 100) <= 80;

        if ($isSuccess) {
            Log::info('Payment successful', [
                'order_id' => $orderId,
                'amount' => $amount,
            ]);

            return PaymentResult::successful();
        }

        Log::warning('Payment failed', [
            'order_id' => $orderId,
            'amount' => $amount,
            'reason' => 'Simulated payment decline',
        ]);

        return PaymentResult::failed('Simulated payment decline.');
    }
}
