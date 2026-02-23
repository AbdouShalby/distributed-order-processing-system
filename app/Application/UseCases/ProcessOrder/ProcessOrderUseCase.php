<?php

declare(strict_types=1);

namespace App\Application\UseCases\ProcessOrder;

use App\Domain\Order\Contracts\OrderRepositoryInterface;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Infrastructure\Broadcasting\Events\OrderFailedEvent;
use App\Infrastructure\Broadcasting\Events\OrderPaidEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PaymentGatewayInterface $paymentGateway,
    ) {}

    public function execute(int $orderId): void
    {
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            Log::warning('ProcessOrder: order not found', ['order_id' => $orderId]);

            return;
        }

        // Idempotency guard — only process PENDING orders
        if (! $order->isProcessable()) {
            Log::info('ProcessOrder: skipping (not PENDING)', [
                'order_id' => $orderId,
                'status' => $order->getStatus()->value,
            ]);

            return;
        }

        // Mark as PROCESSING
        DB::transaction(function () use ($order) {
            $order->markAsProcessing();
            $this->orderRepository->updateStatus($order);
        });

        Log::info('Order processing started', [
            'order_id' => $orderId,
            'event' => 'order_processing_started',
        ]);

        // Simulate payment
        $result = $this->paymentGateway->charge($orderId, $order->getTotalAmount());

        // Update final status inside transaction
        DB::transaction(function () use ($order, $result) {
            if ($result->isSuccessful()) {
                $order->markAsPaid();

                Log::info('Order paid', [
                    'order_id' => $order->getId(),
                    'event' => 'order_paid',
                ]);

                DB::afterCommit(fn () => broadcast(new OrderPaidEvent(
                    orderId: (string) $order->getId(),
                    userId: (string) $order->getUserId(),
                    total: $order->getTotalAmount(),
                    paidAt: now()->toIso8601String(),
                )));
            } else {
                $order->markAsFailed();

                Log::warning('Order payment failed', [
                    'order_id' => $order->getId(),
                    'reason' => $result->getMessage(),
                    'event' => 'order_failed',
                ]);

                DB::afterCommit(fn () => broadcast(new OrderFailedEvent(
                    orderId: (string) $order->getId(),
                    userId: (string) $order->getUserId(),
                    reason: $result->getMessage(),
                    failedAt: now()->toIso8601String(),
                )));
            }

            $this->orderRepository->updateStatus($order);
        });
    }
}
