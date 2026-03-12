<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

final readonly class PaymentResponse
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $transactionId,
        public string $paymentUrl,
        public TransactionStatus $status,
        public ?string $confirmationType = null,
        public array $rawResponse = [],
    ) {}

    /**
     * @param array<string, mixed> $response
     */
    public static function fromApiResponse(array $response): self
    {
        $id = $response['id'] ?? '';
        $status = TransactionStatus::tryFrom($response['status'] ?? '') ?? TransactionStatus::Pending;

        $confirmationType = $response['confirmation']['type'] ?? null;
        $paymentUrl = $response['confirmation']['url']
            ?? $response['confirmation']['confirmUrl']
            ?? $response['confirmation']['confirmData']
            ?? '';

        return new self(
            transactionId: $id,
            paymentUrl: $paymentUrl,
            status: $status,
            confirmationType: $confirmationType,
            rawResponse: $response,
        );
    }

    /**
     * @param array<string, mixed> $rawResponse
     */
    public static function failure(array $rawResponse = []): self
    {
        return new self(
            transactionId: '',
            paymentUrl: '',
            status: TransactionStatus::Canceled,
            rawResponse: $rawResponse,
        );
    }
}
