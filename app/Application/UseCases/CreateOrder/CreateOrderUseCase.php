<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateOrder;

use App\Application\DTOs\CreateOrderDTO;
use App\Application\DTOs\OrderResponseDTO;
use App\Domain\Inventory\Contracts\DistributedLockInterface;
use App\Domain\Inventory\Contracts\ProductRepositoryInterface;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\LockAcquisitionException;
use App\Domain\Order\Contracts\OrderRepositoryInterface;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ValueObjects\OrderItem;
use App\Infrastructure\Queue\Jobs\ProcessOrderJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private DistributedLockInterface $lock,
    ) {}

    public function execute(CreateOrderDTO $dto): OrderResponseDTO
    {
        $traceId = request()?->header('X-Trace-Id') ?? Str::uuid()->toString();

        Log::info('CreateOrder started', [
            'user_id' => $dto->userId,
            'idempotency_key' => $dto->idempotencyKey,
            'trace_id' => $traceId,
        ]);

        // 1. Check idempotency — return existing order if duplicate
        $existingOrder = $this->orderRepository->findByIdempotencyKey($dto->idempotencyKey);
        if ($existingOrder) {
            Log::info('Duplicate request — returning existing order', [
                'order_id' => $existingOrder->getId(),
                'idempotency_key' => $dto->idempotencyKey,
                'trace_id' => $traceId,
            ]);

            return OrderResponseDTO::fromEntity($existingOrder);
        }

        // 2. Extract product IDs for locking
        $productIds = array_map(fn ($item) => $item['product_id'], $dto->items);

        // 3. Acquire distributed locks (ascending order to prevent deadlocks)
        if (! $this->lock->acquireForProducts($productIds)) {
            throw new LockAcquisitionException($productIds);
        }

        try {
            // 4. DB Transaction: check stock → decrement → create order
            $order = DB::transaction(function () use ($dto, $traceId) {

                // 4a. Validate stock + build order items
                $orderItems = [];
                foreach ($dto->items as $item) {
                    $product = $this->productRepository->findByIdForUpdate($item['product_id']);

                    if (! $product) {
                        throw new \DomainException("Product not found: {$item['product_id']}");
                    }

                    if ($product['stock'] < $item['quantity']) {
                        throw InsufficientStockException::forProduct(
                            $item['product_id'],
                            $item['quantity'],
                            (int) $product['stock']
                        );
                    }

                    // 4b. Decrement stock
                    $this->productRepository->decrementStock($item['product_id'], $item['quantity']);

                    $orderItems[] = new OrderItem(
                        productId: $item['product_id'],
                        quantity: $item['quantity'],
                        unitPrice: $product['price'],
                    );
                }

                // 4c. Calculate total server-side
                $totalAmount = Order::calculateTotal($orderItems);

                // 4d. Create order entity
                $order = new Order(
                    id: null,
                    userId: $dto->userId,
                    status: OrderStatus::PENDING,
                    totalAmount: $totalAmount,
                    idempotencyKey: $dto->idempotencyKey,
                );
                $order->setItems($orderItems);

                // 4e. Persist order + items
                $order = $this->orderRepository->save($order);

                Log::info('Order created', [
                    'order_id' => $order->getId(),
                    'total' => $totalAmount,
                    'trace_id' => $traceId,
                    'event' => 'order_created',
                ]);

                return $order;
            });

            // 5. Dispatch async processing AFTER commit
            ProcessOrderJob::dispatch($order->getId(), $traceId)
                ->afterCommit();

            Log::info('ProcessOrderJob dispatched', [
                'order_id' => $order->getId(),
                'trace_id' => $traceId,
            ]);

            return OrderResponseDTO::fromEntity($order);

        } finally {
            // 6. Always release locks
            $this->lock->releaseAll();
        }
    }
}
