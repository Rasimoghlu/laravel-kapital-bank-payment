<?php

namespace Sarkhanrasimoghlu\KapitalBank\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $paymentData
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $orderId,
        public readonly float $amount,
        public readonly array $paymentData = [],
    ) {}
}
