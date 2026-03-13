<?php

namespace Sarkhanrasimoghlu\KapitalBank\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
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

            $result = PaymentResponse::fromApiResponse($response);

            if (empty($result->transactionId)) {
                $this->logger->error('Payment creation failed: missing id', [
                    'response' => $response,
                ]);

                return PaymentResponse::failure($response);
            }

            $this->logger->info('Payment created successfully', [
                'payment_id' => $result->transactionId,
                'confirmation_type' => $result->confirmationType,
            ]);

            $this->events?->dispatch(new PaymentCreated(
                transactionId: $result->transactionId,
                orderId: $request->orderId,
                amount: $request->amount,
                currency: $request->currency->value,
                status: $result->status->value,
                paymentUrl: $result->paymentUrl,
                requestData: $data,
                paymentData: $response,
            ));

            return $result;
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
        $amount = (float) ($response['amount']['value'] ?? 0);
        $currency = $response['amount']['currency'] ?? null;
        $paymentMethod = PaymentMethod::tryFrom(
            strtolower($response['paymentMethod']['type'] ?? '')
        );
        $paidAt = ($response['paid'] ?? false) && isset($response['createdAt'])
            ? new DateTimeImmutable($response['createdAt'])
            : null;

        $this->logger->info('Payment status retrieved', [
            'payment_id' => $paymentId,
            'status' => $status->value,
        ]);

        try {
            DB::transaction(function () use ($paymentId, $status, $response, $paymentMethod, $paidAt) {
                $transaction = DB::table('kapital_bank_transactions')
                    ->where('transaction_id', $paymentId)
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    return;
                }

                $update = [
                    'status' => $status->value,
                    'raw_response' => json_encode($response),
                    'updated_at' => now(),
                ];

                if ($paymentMethod) {
                    $update['payment_method'] = $paymentMethod->value;
                }

                if ($paidAt) {
                    $update['paid_at'] = $paidAt->format('Y-m-d H:i:s');
                }

                DB::table('kapital_bank_transactions')
                    ->where('transaction_id', $paymentId)
                    ->update($update);
            });
        } catch (\Throwable $e) {
            $this->logger->error('Kapital Bank: Failed to sync payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return new PaymentStatus(
            paymentId: $response['id'] ?? $paymentId,
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

            $response = $this->httpClient->put($url, []);

            $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Canceled;

            $this->logger->info('Payment canceled', [
                'payment_id' => $request->paymentId,
                'status' => $status->value,
            ]);

            try {
                DB::table('kapital_bank_transactions')
                    ->where('transaction_id', $request->paymentId)
                    ->update([
                        'status' => $status->value,
                        'raw_response' => json_encode($response),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                $this->logger->error('Kapital Bank: Failed to sync cancel status', [
                    'payment_id' => $request->paymentId,
                    'error' => $e->getMessage(),
                ]);
            }

            return new CancelResponse(
                paymentId: $response['id'] ?? $request->paymentId,
                status: $status,
                cancelationReason: $response['cancelationReason'] ?? null,
                cancelationParty: $response['cancelationParty'] ?? null,
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

            $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Pending;

            $this->logger->info('Refund processed', [
                'payment_id' => $request->paymentId,
                'refund_id' => $response['id'] ?? '',
                'status' => $status->value,
            ]);

            try {
                DB::table('kapital_bank_transactions')
                    ->where('transaction_id', $request->paymentId)
                    ->update([
                        'status' => 'refunded',
                        'raw_response' => json_encode($response),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                $this->logger->error('Kapital Bank: Failed to sync refund status', [
                    'payment_id' => $request->paymentId,
                    'error' => $e->getMessage(),
                ]);
            }

            return new RefundResponse(
                refundId: $response['id'] ?? '',
                status: $status,
                originalId: $response['originalId'] ?? $request->paymentId,
                amount: isset($response['amount']['value']) ? (float) $response['amount']['value'] : $request->amount,
                currency: $response['amount']['currency'] ?? null,
                description: $response['description'] ?? null,
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

        $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Pending;

        $this->logger->info('Refund status retrieved', [
            'refund_id' => $refundId,
            'status' => $status->value,
        ]);

        return new RefundResponse(
            refundId: $response['id'] ?? $refundId,
            status: $status,
            originalId: $response['originalId'] ?? null,
            amount: isset($response['amount']['value']) ? (float) $response['amount']['value'] : null,
            currency: $response['amount']['currency'] ?? null,
            description: $response['description'] ?? null,
            rawResponse: $response,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentData(PaymentRequest $request): array
    {
        $returnUrl = $request->returnUrl
            ?: $this->configuration->getSuccessUrl()
            ?: $this->configuration->getErrorUrl();

        $data = [
            'amount' => [
                'value' => $request->amount,
                'currency' => $request->currency->value,
            ],
            'capture' => $request->capture,
            'description' => $request->description,
            'paymentMethodData' => [
                'type' => $request->paymentMethodType,
            ],
            'confirmation' => [
                'type' => $request->confirmationType,
                'returnUrl' => $returnUrl,
            ],
            'posDetail' => [
                'merchantId' => $this->configuration->getMerchantId(),
                'terminalId' => $this->configuration->getTerminalId(),
            ],
        ];

        $metadata = array_merge(
            ['orderNo' => $request->orderId],
            $request->metadata,
        );

        if (! empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        return $data;
    }
}
