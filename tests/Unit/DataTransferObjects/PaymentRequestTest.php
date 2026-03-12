<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\DataTransferObjects;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\OrderItem;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentRequest;
use Sarkhanrasimoghlu\KapitalBank\Enums\Currency;
use Sarkhanrasimoghlu\KapitalBank\Enums\Language;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

class PaymentRequestTest extends TestCase
{
    #[Test]
    public function it_creates_payment_request_with_valid_data(): void
    {
        $request = new PaymentRequest(
            amount: 100.50,
            currency: Currency::AZN,
            orderId: 'ORDER-001',
            description: 'Test payment',
        );

        $this->assertSame(100.50, $request->amount);
        $this->assertSame(Currency::AZN, $request->currency);
        $this->assertSame('ORDER-001', $request->orderId);
        $this->assertSame('Test payment', $request->description);
        $this->assertSame(Language::AZ, $request->language);
        $this->assertEmpty($request->items);
    }

    #[Test]
    public function it_creates_payment_request_with_items(): void
    {
        $items = [
            new OrderItem(name: 'Product A', price: 50.00, quantity: 1),
            new OrderItem(name: 'Product B', price: 25.25, quantity: 2),
        ];

        $request = new PaymentRequest(
            amount: 100.50,
            currency: Currency::AZN,
            orderId: 'ORDER-002',
            items: $items,
        );

        $this->assertCount(2, $request->items);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $request = new PaymentRequest(
            amount: 50.00,
            currency: Currency::USD,
            orderId: 'ORDER-003',
            description: 'Test',
            successUrl: 'https://example.com/success',
            errorUrl: 'https://example.com/error',
            language: Language::EN,
        );

        $array = $request->toArray();

        $this->assertSame(50.00, $array['amount']);
        $this->assertSame('USD', $array['currency']);
        $this->assertSame('ORDER-003', $array['order_id']);
        $this->assertSame('Test', $array['description']);
        $this->assertSame('https://example.com/success', $array['success_url']);
        $this->assertSame('https://example.com/error', $array['error_url']);
        $this->assertSame('en', $array['language']);
        $this->assertSame([], $array['items']);
    }

    #[Test]
    public function it_throws_exception_when_amount_is_zero(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        new PaymentRequest(
            amount: 0,
            currency: Currency::AZN,
            orderId: 'ORDER-004',
        );
    }

    #[Test]
    public function it_throws_exception_when_amount_is_negative(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        new PaymentRequest(
            amount: -10.00,
            currency: Currency::AZN,
            orderId: 'ORDER-005',
        );
    }

    #[Test]
    public function it_throws_exception_when_order_id_is_empty(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Order ID is required');

        new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: '',
        );
    }
}
