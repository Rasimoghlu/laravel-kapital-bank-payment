<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\DataTransferObjects;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentResponse;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

class PaymentResponseTest extends TestCase
{
    #[Test]
    public function it_creates_response_from_api_response(): void
    {
        $apiResponse = [
            'id' => 'pay_001',
            'status' => 'pending',
            'confirmation' => [
                'type' => 'redirect',
                'url' => 'https://kapitalbank.test/pay/pay_001',
            ],
        ];

        $response = PaymentResponse::fromApiResponse($apiResponse);

        $this->assertSame('pay_001', $response->transactionId);
        $this->assertSame('https://kapitalbank.test/pay/pay_001', $response->paymentUrl);
        $this->assertSame(TransactionStatus::Pending, $response->status);
        $this->assertSame('redirect', $response->confirmationType);
        $this->assertSame($apiResponse, $response->rawResponse);
    }

    #[Test]
    public function it_creates_failure_response(): void
    {
        $response = PaymentResponse::failure(['error' => 'Something went wrong']);

        $this->assertSame('', $response->transactionId);
        $this->assertSame('', $response->paymentUrl);
        $this->assertSame(TransactionStatus::Canceled, $response->status);
        $this->assertSame(['error' => 'Something went wrong'], $response->rawResponse);
    }

    #[Test]
    public function it_creates_response_with_constructor(): void
    {
        $response = new PaymentResponse(
            transactionId: 'pay_002',
            paymentUrl: 'https://kapitalbank.test/pay/pay_002',
            status: TransactionStatus::Succeeded,
            confirmationType: 'redirect',
        );

        $this->assertSame('pay_002', $response->transactionId);
        $this->assertSame(TransactionStatus::Succeeded, $response->status);
        $this->assertSame('redirect', $response->confirmationType);
        $this->assertSame([], $response->rawResponse);
    }
}
