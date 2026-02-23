<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Order\Contracts\OrderRepositoryInterface;
use App\Domain\Order\Entities\Order;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\ValueObjects\OrderItem;
use Illuminate\Support\Facades\DB;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        $row = DB::table('orders')->where('id', $id)->first();

        if (! $row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    public function findByIdempotencyKey(string $key): ?Order
    {
        $row = DB::table('orders')->where('idempotency_key', $key)->first();

        if (! $row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    public function save(Order $order): Order
    {
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $order->getUserId(),
            'status' => $order->getStatus()->value,
            'total_amount' => $order->getTotalAmount(),
            'idempotency_key' => $order->getIdempotencyKey(),
            'cancelled_at' => $order->getCancelledAt()?->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order->setId($orderId);

        // Save order items
        foreach ($order->getItems() as $item) {
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $order;
    }

    public function updateStatus(Order $order): void
    {
        DB::table('orders')
            ->where('id', $order->getId())
            ->update([
                'status' => $order->getStatus()->value,
                'cancelled_at' => $order->getCancelledAt()?->format('Y-m-d H:i:s'),
                'updated_at' => now(),
            ]);
    }

    public function findByUserId(int $userId, ?string $status = null, int $page = 1, int $perPage = 15): array
    {
        $query = DB::table('orders')->where('user_id', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return $rows->map(fn ($row) => $this->mapToEntity($row))->all();
    }

    public function countByUserId(int $userId, ?string $status = null): int
    {
        $query = DB::table('orders')->where('user_id', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    // ─── Private ──────────────────────────────────────

    private function mapToEntity(object $row): Order
    {
        $order = new Order(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            status: OrderStatus::from($row->status),
            totalAmount: (string) $row->total_amount,
            idempotencyKey: $row->idempotency_key,
            cancelledAt: $row->cancelled_at ? new \DateTimeImmutable($row->cancelled_at) : null,
            createdAt: $row->created_at ? new \DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
        );

        // Load items
        $items = DB::table('order_items')
            ->where('order_id', $row->id)
            ->get()
            ->map(fn ($item) => new OrderItem(
                productId: (int) $item->product_id,
                quantity: (int) $item->quantity,
                unitPrice: (string) $item->unit_price,
            ))
            ->all();

        $order->setItems($items);

        return $order;
    }
}
