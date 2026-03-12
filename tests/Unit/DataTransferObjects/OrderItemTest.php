<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\DataTransferObjects;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\OrderItem;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

class OrderItemTest extends TestCase
{
    #[Test]
    public function it_creates_order_item_with_valid_data(): void
    {
        $item = new OrderItem(
            name: 'Test Product',
            price: 10.50,
            quantity: 2,
        );

        $this->assertSame('Test Product', $item->name);
        $this->assertSame(10.50, $item->price);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(21.00, $item->amount);
    }

    #[Test]
    public function it_calculates_amount_correctly(): void
    {
        $item = new OrderItem(name: 'Item', price: 9.99, quantity: 3);
        $this->assertSame(29.97, $item->amount);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $item = new OrderItem(name: 'Item', price: 5.00, quantity: 1);

        $expected = [
            'name' => 'Item',
            'price' => 5.00,
            'quantity' => 1,
            'amount' => 5.00,
        ];

        $this->assertSame($expected, $item->toArray());
    }

    #[Test]
    public function it_throws_exception_when_name_is_empty(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Item name is required');

        new OrderItem(name: '', price: 10.00, quantity: 1);
    }

    #[Test]
    public function it_throws_exception_when_price_is_zero(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Item price must be greater than zero');

        new OrderItem(name: 'Item', price: 0, quantity: 1);
    }

    #[Test]
    public function it_throws_exception_when_price_is_negative(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Item price must be greater than zero');

        new OrderItem(name: 'Item', price: -5.00, quantity: 1);
    }

    #[Test]
    public function it_throws_exception_when_quantity_is_zero(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Item quantity must be greater than zero');

        new OrderItem(name: 'Item', price: 10.00, quantity: 0);
    }

    #[Test]
    public function it_throws_exception_when_quantity_is_negative(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Item quantity must be greater than zero');

        new OrderItem(name: 'Item', price: 10.00, quantity: -1);
    }
}
