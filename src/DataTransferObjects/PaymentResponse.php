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
        public array $rawResponse = [],
    ) {}

    /**
     * @param array<string, mixed> $rawResponse
     */
    public static function success(string $transactionId, string $paymentUrl, array $rawResponse = []): self
    {
        return new self(
            transactionId: $transactionId,
            paymentUrl: $paymentUrl,
            status: TransactionStatus::Pending,
            rawResponse: $rawResponse,
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
