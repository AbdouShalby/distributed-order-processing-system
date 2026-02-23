<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\ValueObjects\OrderItem;

class OrderResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $status,
        public readonly string $totalAmount,
        public readonly string $idempotencyKey,
        public readonly ?string $cancelledAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $items,
    ) {}

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId(),
            userId: $order->getUserId(),
            status: $order->getStatus()->value,
            totalAmount: $order->getTotalAmount(),
            idempotencyKey: $order->getIdempotencyKey(),
            cancelledAt: $order->getCancelledAt()?->format('Y-m-d\TH:i:s\Z'),
            createdAt: $order->getCreatedAt()?->format('Y-m-d\TH:i:s\Z') ?? now()->toIso8601String(),
            updatedAt: $order->getUpdatedAt()?->format('Y-m-d\TH:i:s\Z') ?? now()->toIso8601String(),
            items: array_map(fn (OrderItem $item) => [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
            ], $order->getItems()),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'status' => $this->status,
            'total_amount' => $this->totalAmount,
            'idempotency_key' => $this->idempotencyKey,
            'cancelled_at' => $this->cancelledAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'items' => $this->items,
        ];
    }
}
