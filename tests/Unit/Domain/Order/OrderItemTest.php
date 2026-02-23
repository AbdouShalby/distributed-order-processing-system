<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\ValueObjects\OrderItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderItemTest extends TestCase
{
    #[Test]
    public function getters_return_constructor_values(): void
    {
        $item = new OrderItem(
            productId: 5,
            quantity: 3,
            unitPrice: '49.99',
        );

        $this->assertSame(5, $item->getProductId());
        $this->assertSame(3, $item->getQuantity());
        $this->assertSame('49.99', $item->getUnitPrice());
    }

    #[Test]
    public function line_total_calculated_correctly(): void
    {
        $item = new OrderItem(
            productId: 1,
            quantity: 4,
            unitPrice: '25.50',
        );

        // 4 × 25.50 = 102.00
        $this->assertSame('102.00', $item->getLineTotal());
    }

    #[Test]
    public function line_total_single_item(): void
    {
        $item = new OrderItem(
            productId: 1,
            quantity: 1,
            unitPrice: '999.99',
        );

        $this->assertSame('999.99', $item->getLineTotal());
    }

    #[Test]
    public function line_total_precision_no_floating_point_errors(): void
    {
        // This would fail with float arithmetic: 0.1 + 0.2 !== 0.3
        $item = new OrderItem(
            productId: 1,
            quantity: 3,
            unitPrice: '0.10',
        );

        $this->assertSame('0.30', $item->getLineTotal());
    }
}
