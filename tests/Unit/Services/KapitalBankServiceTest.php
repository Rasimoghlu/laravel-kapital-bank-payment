<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;
use Sarkhanrasimoghlu\KapitalBank\Enums\Currency;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\HttpException;
use Sarkhanrasimoghlu\KapitalBank\Services\KapitalBankService;

class KapitalBankServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;

    private ConfigurationInterface $configuration;

    private LoggerInterface $logger;

    private KapitalBankService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->configuration->method('getBaseUrl')->willReturn('https://api.kapitalbank.test');
        $this->configuration->method('getMerchantId')->willReturn('E1040009');
        $this->configuration->method('getTerminalId')->willReturn('E1040009');
        $this->configuration->method('getSuccessUrl')->willReturn('https://example.com/success');
        $this->configuration->method('getErrorUrl')->willReturn('https://example.com/error');
        $this->configuration->method('getCallbackUrl')->willReturn('https://example.com/callback');

        $this->service = new KapitalBankService(
            httpClient: $this->httpClient,
            configuration: $this->configuration,
            logger: $this->logger,
        );
    }

    #[Test]
    public function it_creates_payment_successfully(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'pay_001',
            'status' => 'pending',
            'confirmation' => [
                'type' => 'redirect',
                'url' => 'https://checkout.kapitalbank.az/pay/pay_001',
                'returnUrl' => 'https://example.com/success',
            ],
        ]);

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-001',
            description: 'Test payment',
        );

        $response = $this->service->createPayment($request);

        $this->assertSame('pay_001', $response->transactionId);
        $this->assertSame('https://checkout.kapitalbank.az/pay/pay_001', $response->paymentUrl);
        $this->assertSame(TransactionStatus::Pending, $response->status);
        $this->assertSame('redirect', $response->confirmationType);
    }

    #[Test]
    public function it_creates_payment_with_qr_confirmation(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'pay_002',
            'status' => 'pending',
            'confirmation' => [
                'type' => 'qr',
                'confirmData' => 'birbank://v1/payments?paymentId=pay_002',
            ],
        ]);

        $request = new PaymentRequest(
            amount: 10.00,
            currency: Currency::AZN,
            orderId: 'ORDER-002',
            confirmationType: 'QR',
            paymentMethodType: 'BIRBANK',
        );

        $response = $this->service->createPayment($request);

        $this->assertSame('pay_002', $response->transactionId);
        $this->assertSame('birbank://v1/payments?paymentId=pay_002', $response->paymentUrl);
        $this->assertSame('qr', $response->confirmationType);
    }

    #[Test]
    public function it_returns_failure_response_when_api_returns_incomplete_data(): void
    {
        $this->httpClient->method('post')->willReturn([
            'error' => 'Invalid request',
        ]);

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-003',
        );

        $response = $this->service->createPayment($request);

        $this->assertSame('', $response->transactionId);
        $this->assertSame(TransactionStatus::Canceled, $response->status);
    }

    #[Test]
    public function it_throws_exception_on_http_error_during_payment(): void
    {
        $this->httpClient->method('post')->willThrowException(
            HttpException::connectionFailed('https://api.kapitalbank.test/v1/payments'),
        );

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-004',
        );

        $this->expectException(HttpException::class);

        $this->service->createPayment($request);
    }

    #[Test]
    public function it_gets_payment_status_via_get(): void
    {
        $this->httpClient->method('get')->willReturn([
            'id' => 'pay_001',
            'status' => 'succeeded',
            'amount' => ['value' => 100.00, 'currency' => 'azn'],
            'paymentMethod' => ['type' => 'birbank'],
            'paid' => true,
            'createdAt' => '2024-01-01T12:00:00Z',
        ]);

        $status = $this->service->getPaymentStatus('pay_001');

        $this->assertSame('pay_001', $status->paymentId);
        $this->assertSame(TransactionStatus::Succeeded, $status->status);
        $this->assertSame(100.00, $status->amount);
        $this->assertSame('azn', $status->currency);
        $this->assertNotNull($status->paidAt);
        $this->assertNotEmpty($status->rawResponse);
    }

    #[Test]
    public function it_handles_unknown_status(): void
    {
        $this->httpClient->method('get')->willReturn([
            'id' => 'pay_002',
            'status' => 'UNKNOWN_STATUS',
            'amount' => ['value' => 50.00, 'currency' => 'azn'],
        ]);

        $status = $this->service->getPaymentStatus('pay_002');

        $this->assertSame(TransactionStatus::Pending, $status->status);
    }

    #[Test]
    public function it_cancels_payment(): void
    {
        $this->httpClient->method('put')->willReturn([
            'id' => 'pay_001',
            'status' => 'canceled',
            'cancelationReason' => 'canceled_by_merchant',
            'cancelationParty' => 'merchant',
        ]);

        $request = new CancelRequest(paymentId: 'pay_001');

        $response = $this->service->cancelPayment($request);

        $this->assertSame('pay_001', $response->paymentId);
        $this->assertSame(TransactionStatus::Canceled, $response->status);
        $this->assertSame('canceled_by_merchant', $response->cancelationReason);
        $this->assertSame('merchant', $response->cancelationParty);
    }

    #[Test]
    public function it_processes_refund(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'ref_001',
            'originalId' => 'pay_001',
            'status' => 'pending',
            'amount' => ['value' => 100.00, 'currency' => 'azn'],
            'description' => 'refund',
        ]);

        $request = new RefundRequest(paymentId: 'pay_001');
        $response = $this->service->refund($request);

        $this->assertSame('ref_001', $response->refundId);
        $this->assertSame(TransactionStatus::Pending, $response->status);
        $this->assertSame('pay_001', $response->originalId);
        $this->assertSame(100.00, $response->amount);
    }

    #[Test]
    public function it_processes_partial_refund(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'ref_002',
            'originalId' => 'pay_001',
            'status' => 'pending',
            'amount' => ['value' => 4.00, 'currency' => 'azn'],
            'description' => 'partial refund',
        ]);

        $request = new RefundRequest(paymentId: 'pay_001', amount: 4.00);
        $response = $this->service->refund($request);

        $this->assertSame('ref_002', $response->refundId);
        $this->assertSame(4.00, $response->amount);
    }

    #[Test]
    public function it_gets_refund_status(): void
    {
        $this->httpClient->method('get')->willReturn([
            'id' => 'ref_001',
            'originalId' => 'pay_001',
            'status' => 'succeeded',
            'amount' => ['value' => 10.00, 'currency' => 'azn'],
            'description' => 'refund',
        ]);

        $response = $this->service->getRefundStatus('ref_001');

        $this->assertSame('ref_001', $response->refundId);
        $this->assertSame(TransactionStatus::Succeeded, $response->status);
        $this->assertSame('pay_001', $response->originalId);
    }

    #[Test]
    public function it_throws_exception_on_http_error_during_refund(): void
    {
        $this->httpClient->method('post')->willThrowException(
            HttpException::serverError(500, 'Internal Server Error'),
        );

        $request = new RefundRequest(paymentId: 'pay_001');

        $this->expectException(HttpException::class);

        $this->service->refund($request);
    }

    #[Test]
    public function it_logs_payment_creation(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'pay_001',
            'status' => 'pending',
            'confirmation' => [
                'type' => 'redirect',
                'url' => 'https://checkout.kapitalbank.az/pay/pay_001',
            ],
        ]);

        $this->logger->expects($this->atLeast(2))
            ->method('info');

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-001',
        );

        $this->service->createPayment($request);
    }

    #[Test]
    public function it_logs_errors_on_failure(): void
    {
        $this->httpClient->method('post')->willThrowException(
            HttpException::connectionFailed('https://api.kapitalbank.test/v1/payments'),
        );

        $this->logger->expects($this->once())
            ->method('error');

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-001',
        );

        try {
            $this->service->createPayment($request);
        } catch (HttpException) {
            // Expected
        }
    }
}
