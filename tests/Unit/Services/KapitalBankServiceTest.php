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
use Sarkhanrasimoghlu\KapitalBank\Enums\CancellationReason;
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
        $this->configuration->method('getMerchantId')->willReturn('test-merchant');
        $this->configuration->method('getTerminalId')->willReturn('test-terminal');
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
            'redirect_url' => 'https://kapitalbank.test/pay/pay_001',
        ]);

        $request = new PaymentRequest(
            amount: 100.00,
            currency: Currency::AZN,
            orderId: 'ORDER-001',
            description: 'Test payment',
        );

        $response = $this->service->createPayment($request);

        $this->assertSame('pay_001', $response->transactionId);
        $this->assertSame('https://kapitalbank.test/pay/pay_001', $response->paymentUrl);
        $this->assertSame(TransactionStatus::Pending, $response->status);
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
            orderId: 'ORDER-002',
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
            orderId: 'ORDER-003',
        );

        $this->expectException(HttpException::class);

        $this->service->createPayment($request);
    }

    #[Test]
    public function it_gets_payment_status_via_get(): void
    {
        $this->httpClient->method('get')->willReturn([
            'status' => 'succeeded',
            'amount' => 100.00,
            'currency' => 'AZN',
            'payment_method' => 'card',
            'paid_at' => '2024-01-01T12:00:00Z',
        ]);

        $status = $this->service->getPaymentStatus('pay_001');

        $this->assertSame('pay_001', $status->paymentId);
        $this->assertSame(TransactionStatus::Succeeded, $status->status);
        $this->assertSame(100.00, $status->amount);
        $this->assertSame('AZN', $status->currency);
        $this->assertNotNull($status->paymentMethod);
        $this->assertNotNull($status->paidAt);
        $this->assertNotEmpty($status->rawResponse);
    }

    #[Test]
    public function it_handles_unknown_status(): void
    {
        $this->httpClient->method('get')->willReturn([
            'status' => 'UNKNOWN_STATUS',
            'amount' => 50.00,
        ]);

        $status = $this->service->getPaymentStatus('pay_002');

        $this->assertSame(TransactionStatus::Pending, $status->status);
    }

    #[Test]
    public function it_cancels_payment(): void
    {
        $this->httpClient->method('put')->willReturn([
            'status' => 'canceled',
        ]);

        $request = new CancelRequest(
            paymentId: 'pay_001',
            reason: CancellationReason::CanceledByMerchant,
        );

        $response = $this->service->cancelPayment($request);

        $this->assertSame('pay_001', $response->paymentId);
        $this->assertSame(TransactionStatus::Canceled, $response->status);
    }

    #[Test]
    public function it_processes_refund(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'ref_001',
            'status' => 'succeeded',
            'message' => 'Refund processed',
        ]);

        $request = new RefundRequest(paymentId: 'pay_001');
        $response = $this->service->refund($request);

        $this->assertTrue($response->success);
        $this->assertSame('Refund processed', $response->message);
        $this->assertSame('ref_001', $response->refundId);
        $this->assertSame(TransactionStatus::Succeeded, $response->status);
    }

    #[Test]
    public function it_processes_partial_refund(): void
    {
        $this->httpClient->method('post')->willReturn([
            'id' => 'ref_002',
            'status' => 'succeeded',
            'message' => 'Partial refund processed',
        ]);

        $request = new RefundRequest(paymentId: 'pay_001', amount: 25.00);
        $response = $this->service->refund($request);

        $this->assertTrue($response->success);
        $this->assertSame('ref_002', $response->refundId);
    }

    #[Test]
    public function it_handles_failed_refund(): void
    {
        $this->httpClient->method('post')->willReturn([
            'status' => 'canceled',
            'message' => 'Insufficient balance',
        ]);

        $request = new RefundRequest(paymentId: 'pay_001');
        $response = $this->service->refund($request);

        $this->assertFalse($response->success);
        $this->assertSame('Insufficient balance', $response->message);
    }

    #[Test]
    public function it_gets_refund_status(): void
    {
        $this->httpClient->method('get')->willReturn([
            'status' => 'succeeded',
            'message' => 'Refund completed',
        ]);

        $response = $this->service->getRefundStatus('ref_001');

        $this->assertTrue($response->success);
        $this->assertSame('ref_001', $response->refundId);
        $this->assertSame(TransactionStatus::Succeeded, $response->status);
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
            'redirect_url' => 'https://kapitalbank.test/pay/pay_001',
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
