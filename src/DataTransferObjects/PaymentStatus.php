<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use DateTimeImmutable;
use Sarkhanrasimoghlu\KapitalBank\Enums\PaymentMethod;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;

final readonly class PaymentStatus
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $paymentId,
        public TransactionStatus $status,
        public float $amount,
        public ?string $currency = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?DateTimeImmutable $paidAt = null,
        public array $rawResponse = [],
    ) {}
}
