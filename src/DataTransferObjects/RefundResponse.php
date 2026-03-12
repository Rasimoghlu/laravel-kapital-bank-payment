<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

final readonly class RefundResponse
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $refundId,
        public TransactionStatus $status,
        public ?string $originalId = null,
        public ?float $amount = null,
        public ?string $currency = null,
        public ?string $description = null,
        public array $rawResponse = [],
    ) {}
}
