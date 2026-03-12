<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

final readonly class RefundResponse
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public bool $success,
        public string $message,
        public string $refundId = '',
        public ?TransactionStatus $status = null,
        public array $rawResponse = [],
    ) {}
}
