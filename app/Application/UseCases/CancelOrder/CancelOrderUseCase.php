<?php

declare(strict_types=1);

namespace App\Application\UseCases\CancelOrder;

use App\Application\DTOs\OrderResponseDTO;
use App\Domain\Inventory\Contracts\ProductRepositoryInterface;
use App\Domain\Order\Contracts\OrderRepositoryInterface;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\OrderNotCancellableException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Infrastructure\Broadcasting\Events\OrderCancelledEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $orderId): OrderResponseDTO
    {
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new OrderNotFoundException($orderId);
        }

        // Idempotent: already cancelled → return success
        if ($order->getStatus() === OrderStatus::CANCELLED) {
            Log::info('CancelOrder: already cancelled (no-op)', [
                'order_id' => $orderId,
            ]);

            return OrderResponseDTO::fromEntity($order);
        }

        // Only PENDING orders can be cancelled
        if (! $order->isCancellable()) {
            throw new OrderNotCancellableException($order->getStatus()->value);
        }

        // Cancel + restore stock inside transaction
        DB::transaction(function () use ($order) {
            $order->markAsCancelled();
            $this->orderRepository->updateStatus($order);

            // Restore stock for each item
            foreach ($order->getItems() as $item) {
                $this->productRepository->incrementStock(
                    $item->getProductId(),
                    $item->getQuantity()
                );
            }

            Log::info('Order cancelled + stock restored', [
                'order_id' => $order->getId(),
                'items_restored' => count($order->getItems()),
                'event' => 'order_cancelled',
            ]);

            DB::afterCommit(fn () => broadcast(new OrderCancelledEvent(
                orderId: (string) $order->getId(),
                userId: (string) $order->getUserId(),
                cancelledAt: now()->toIso8601String(),
            )));
        });

        // Reload to get updated timestamps
        $updatedOrder = $this->orderRepository->findById($orderId);

        return OrderResponseDTO::fromEntity($updatedOrder);
    }
}
