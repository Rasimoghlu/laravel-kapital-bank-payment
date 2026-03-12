<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\DataTransferObjects;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

class RefundRequestTest extends TestCase
{
    #[Test]
    public function it_creates_full_refund_request(): void
    {
        $request = new RefundRequest(paymentId: 'pay_001');

        $this->assertSame('pay_001', $request->paymentId);
        $this->assertNull($request->amount);
    }

    #[Test]
    public function it_creates_partial_refund_request(): void
    {
        $request = new RefundRequest(paymentId: 'pay_001', amount: 50.00);

        $this->assertSame('pay_001', $request->paymentId);
        $this->assertSame(50.00, $request->amount);
    }

    #[Test]
    public function it_converts_full_refund_to_array(): void
    {
        $request = new RefundRequest(paymentId: 'pay_001');

        $expected = ['payment_id' => 'pay_001'];

        $this->assertSame($expected, $request->toArray());
    }

    #[Test]
    public function it_converts_partial_refund_to_array(): void
    {
        $request = new RefundRequest(paymentId: 'pay_001', amount: 25.00);

        $expected = [
            'payment_id' => 'pay_001',
            'amount' => 25.00,
        ];

        $this->assertSame($expected, $request->toArray());
    }

    #[Test]
    public function it_throws_exception_when_payment_id_is_empty(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment ID is required');

        new RefundRequest(paymentId: '');
    }

    #[Test]
    public function it_throws_exception_when_amount_is_negative(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        new RefundRequest(paymentId: 'pay_001', amount: -10.00);
    }

    #[Test]
    public function it_throws_exception_when_amount_is_zero(): void
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        new RefundRequest(paymentId: 'pay_001', amount: 0);
    }
}
