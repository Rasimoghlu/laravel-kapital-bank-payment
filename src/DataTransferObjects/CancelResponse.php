<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

final readonly class CancelResponse
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $paymentId,
        public TransactionStatus $status,
        public ?string $cancelationReason = null,
        public ?string $cancelationParty = null,
        public array $rawResponse = [],
    ) {}
}
