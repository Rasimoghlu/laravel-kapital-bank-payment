<?php

namespace Sarkhanrasimoghlu\KapitalBank\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $paymentData
     * @param array<string, mixed> $requestData
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $orderId,
        public readonly float $amount,
        public readonly string $currency = '',
        public readonly string $status = 'pending',
        public readonly string $paymentUrl = '',
        public readonly array $requestData = [],
        public readonly array $paymentData = [],
    ) {}
}
