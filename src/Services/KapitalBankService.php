<?php

namespace Sarkhanrasimoghlu\KapitalBank\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\KapitalBankServiceInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelResponse;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentResponse;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentStatus;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundResponse;
use Sarkhanrasimoghlu\KapitalBank\Enums\PaymentMethod;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentCreated;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\KapitalBankException;

class KapitalBankService implements KapitalBankServiceInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $this->logger->info('Creating payment', [
            'order_id' => $request->orderId,
            'amount' => $request->amount,
            'currency' => $request->currency->value,
        ]);

        try {
            $data = $this->buildPaymentData($request);

            $url = rtrim($this->configuration->getBaseUrl(), '/') . '/v1/payments';

            $response = $this->httpClient->post($url, $data);

            $transactionId = $response['id'] ?? '';
            $paymentUrl = $response['redirect_url'] ?? '';

            if (empty($transactionId) || empty($paymentUrl)) {
                $this->logger->error('Payment creation failed: missing id or redirect_url', [
                    'response' => $response,
                ]);

                return PaymentResponse::failure($response);
            }

            $this->logger->info('Payment created successfully', [
                'payment_id' => $transactionId,
                'redirect_url' => $paymentUrl,
            ]);

            $this->events?->dispatch(new PaymentCreated($transactionId, $request->orderId, $request->amount, $response));

            return PaymentResponse::success($transactionId, $paymentUrl, $response);
        } catch (KapitalBankException $e) {
            $this->logger->error('Payment creation failed', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        }
    }

    public function getPaymentStatus(string $paymentId): PaymentStatus
    {
        $this->logger->info('Getting payment status', [
            'payment_id' => $paymentId,
        ]);

        $url = rtrim($this->configuration->getBaseUrl(), '/') . '/v1/payments/' . $paymentId;

        $response = $this->httpClient->get($url);

        $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Pending;
        $amount = (float) ($response['amount'] ?? 0);
        $currency = $response['currency'] ?? null;
        $paymentMethod = PaymentMethod::tryFrom($response['payment_method'] ?? '');
        $paidAt = isset($response['paid_at'])
            ? new DateTimeImmutable($response['paid_at'])
            : null;

        $this->logger->info('Payment status retrieved', [
            'payment_id' => $paymentId,
            'status' => $status->value,
        ]);

        return new PaymentStatus(
            paymentId: $paymentId,
            status: $status,
            amount: $amount,
            currency: $currency,
            paymentMethod: $paymentMethod,
            paidAt: $paidAt,
            rawResponse: $response,
        );
    }

    public function cancelPayment(CancelRequest $request): CancelResponse
    {
        $this->logger->info('Canceling payment', [
            'payment_id' => $request->paymentId,
        ]);

        try {
            $url = rtrim($this->configuration->getBaseUrl(), '/') . '/v1/payments/' . $request->paymentId . '/cancel';

            $response = $this->httpClient->put($url, $request->toArray());

            $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Canceled;

            $this->logger->info('Payment canceled', [
                'payment_id' => $request->paymentId,
                'status' => $status->value,
            ]);

            return new CancelResponse(
                paymentId: $request->paymentId,
                status: $status,
                rawResponse: $response,
            );
        } catch (KapitalBankException $e) {
            $this->logger->error('Payment cancellation failed', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        }
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $this->logger->info('Processing refund', [
            'payment_id' => $request->paymentId,
            'amount' => $request->amount,
        ]);

        try {
            $url = rtrim($this->configuration->getBaseUrl(), '/') . '/v1/refunds';

            $response = $this->httpClient->post($url, $request->toArray());

            $success = in_array($response['status'] ?? '', ['succeeded', 'pending']);
            $message = $response['message'] ?? '';
            $refundId = $response['id'] ?? '';
            $status = TransactionStatus::tryFrom($response['status'] ?? '');

            $this->logger->info('Refund processed', [
                'payment_id' => $request->paymentId,
                'success' => $success,
                'refund_id' => $refundId,
            ]);

            return new RefundResponse(
                success: $success,
                message: $message,
                refundId: $refundId,
                status: $status,
                rawResponse: $response,
            );
        } catch (KapitalBankException $e) {
            $this->logger->error('Refund failed', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        }
    }

    public function getRefundStatus(string $refundId): RefundResponse
    {
        $this->logger->info('Getting refund status', [
            'refund_id' => $refundId,
        ]);

        $url = rtrim($this->configuration->getBaseUrl(), '/') . '/v1/refunds/' . $refundId;

        $response = $this->httpClient->get($url);

        $status = TransactionStatus::tryFrom($response['status'] ?? '');
        $success = in_array($response['status'] ?? '', ['succeeded', 'pending']);
        $message = $response['message'] ?? '';

        $this->logger->info('Refund status retrieved', [
            'refund_id' => $refundId,
            'status' => $response['status'] ?? 'unknown',
        ]);

        return new RefundResponse(
            success: $success,
            message: $message,
            refundId: $refundId,
            status: $status,
            rawResponse: $response,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentData(PaymentRequest $request): array
    {
        $data = [
            'terminal_id' => $this->configuration->getTerminalId(),
            'amount' => $request->amount,
            'currency' => $request->currency->value,
            'order_id' => $request->orderId,
            'description' => $request->description,
            'language' => $request->language->value,
            'success_url' => $request->successUrl ?: $this->configuration->getSuccessUrl(),
            'error_url' => $request->errorUrl ?: $this->configuration->getErrorUrl(),
            'callback_url' => $this->configuration->getCallbackUrl(),
        ];

        if (! empty($request->items)) {
            $data['items'] = array_map(fn ($item) => $item->toArray(), $request->items);
        }

        return $data;
    }
}
