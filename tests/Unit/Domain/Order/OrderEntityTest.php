<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\Entities\Order;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\ValueObjects\OrderItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderEntityTest extends TestCase
{
    private function makeOrder(OrderStatus $status = OrderStatus::PENDING): Order
    {
        return new Order(
            id: 1,
            userId: 10,
            status: $status,
            totalAmount: '100.00',
            idempotencyKey: 'idem-123',
        );
    }

    // ─── State Transitions ────────────────────────────

    #[Test]
    public function pending_order_can_transition_to_processing(): void
    {
        $order = $this->makeOrder();
        $order->markAsProcessing();

        $this->assertSame(OrderStatus::PROCESSING, $order->getStatus());
    }

    #[Test]
    public function processing_order_can_transition_to_paid(): void
    {
        $order = $this->makeOrder();
        $order->markAsProcessing();
        $order->markAsPaid();

        $this->assertSame(OrderStatus::PAID, $order->getStatus());
    }

    #[Test]
    public function processing_order_can_transition_to_failed(): void
    {
        $order = $this->makeOrder();
        $order->markAsProcessing();
        $order->markAsFailed();

        $this->assertSame(OrderStatus::FAILED, $order->getStatus());
    }

    #[Test]
    public function pending_order_can_be_cancelled(): void
    {
        $order = $this->makeOrder();
        $order->markAsCancelled();

        $this->assertSame(OrderStatus::CANCELLED, $order->getStatus());
        $this->assertNotNull($order->getCancelledAt());
    }

    #[Test]
    public function paid_order_cannot_transition(): void
    {
        $order = $this->makeOrder();
        $order->markAsProcessing();
        $order->markAsPaid();

        $this->expectException(InvalidOrderTransitionException::class);
        $order->markAsCancelled();
    }

    #[Test]
    public function failed_order_cannot_transition(): void
    {
        $order = $this->makeOrder();
        $order->markAsProcessing();
        $order->markAsFailed();

        $this->expectException(InvalidOrderTransitionException::class);
        $order->markAsProcessing();
    }

    #[Test]
    public function cancelled_order_cannot_transition(): void
    {
        $order = $this->makeOrder();
        $order->markAsCancelled();

        $this->expectException(InvalidOrderTransitionException::class);
        $order->markAsProcessing();
    }

    // ─── Business Logic ───────────────────────────────

    #[Test]
    public function is_cancellable_only_when_pending(): void
    {
        $this->assertTrue($this->makeOrder(OrderStatus::PENDING)->isCancellable());
        $this->assertFalse($this->makeOrder(OrderStatus::PROCESSING)->isCancellable());
        $this->assertFalse($this->makeOrder(OrderStatus::PAID)->isCancellable());
        $this->assertFalse($this->makeOrder(OrderStatus::FAILED)->isCancellable());
        $this->assertFalse($this->makeOrder(OrderStatus::CANCELLED)->isCancellable());
    }

    #[Test]
    public function is_processable_only_when_pending(): void
    {
        $this->assertTrue($this->makeOrder(OrderStatus::PENDING)->isProcessable());
        $this->assertFalse($this->makeOrder(OrderStatus::PROCESSING)->isProcessable());
        $this->assertFalse($this->makeOrder(OrderStatus::PAID)->isProcessable());
    }

    // ─── Total Calculation ────────────────────────────

    #[Test]
    public function calculate_total_from_items(): void
    {
        $items = [
            new OrderItem(productId: 1, quantity: 2, unitPrice: '29.99'),
            new OrderItem(productId: 2, quantity: 1, unitPrice: '149.99'),
        ];

        // 2 × 29.99 = 59.98, + 149.99 = 209.97
        $this->assertSame('209.97', Order::calculateTotal($items));
    }

    #[Test]
    public function calculate_total_empty_items_returns_zero(): void
    {
        $this->assertSame('0', Order::calculateTotal([]));
    }

    #[Test]
    public function calculate_total_precision(): void
    {
        $items = [
            new OrderItem(productId: 1, quantity: 3, unitPrice: '9.99'),
        ];

        // 3 × 9.99 = 29.97
        $this->assertSame('29.97', Order::calculateTotal($items));
    }

    // ─── Getters ──────────────────────────────────────

    #[Test]
    public function getters_return_constructor_values(): void
    {
        $order = new Order(
            id: 42,
            userId: 7,
            status: OrderStatus::PENDING,
            totalAmount: '500.00',
            idempotencyKey: 'key-abc',
        );

        $this->assertSame(42, $order->getId());
        $this->assertSame(7, $order->getUserId());
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertSame('500.00', $order->getTotalAmount());
        $this->assertSame('key-abc', $order->getIdempotencyKey());
        $this->assertNull($order->getCancelledAt());
        $this->assertEmpty($order->getItems());
    }

    #[Test]
    public function items_can_be_set(): void
    {
        $order = $this->makeOrder();
        $items = [
            new OrderItem(productId: 1, quantity: 1, unitPrice: '10.00'),
        ];

        $order->setItems($items);

        $this->assertCount(1, $order->getItems());
        $this->assertSame(1, $order->getItems()[0]->getProductId());
    }
}
