<?php

namespace Sarkhanrasimoghlu\KapitalBank\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $refundData
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $refundId = '',
        public readonly array $refundData = [],
    ) {}
}
