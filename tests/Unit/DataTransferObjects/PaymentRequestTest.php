<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\DataTransferObjects;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
        $this->assertTrue($request->capture);
        $this->assertSame('BANK_CARD', $request->paymentMethodType);
        $this->assertSame('REDIRECT', $request->confirmationType);
        $this->assertSame('', $request->returnUrl);
        $this->assertSame([], $request->metadata);
    }

    #[Test]
    public function it_creates_payment_request_with_custom_properties(): void
    {
        $request = new PaymentRequest(
            amount: 100.50,
            currency: Currency::AZN,
            orderId: 'ORDER-002',
            capture: false,
            paymentMethodType: 'BIRBANK',
            confirmationType: 'QR',
            returnUrl: 'https://example.com/return',
            metadata: ['key' => 'value'],
            language: Language::EN,
        );

        $this->assertFalse($request->capture);
        $this->assertSame('BIRBANK', $request->paymentMethodType);
        $this->assertSame('QR', $request->confirmationType);
        $this->assertSame('https://example.com/return', $request->returnUrl);
        $this->assertSame(['key' => 'value'], $request->metadata);
        $this->assertSame(Language::EN, $request->language);
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
