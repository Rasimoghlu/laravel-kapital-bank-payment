<?php

namespace Sarkhanrasimoghlu\KapitalBank\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $transaction
     * @param array<string, mixed> $callbackData
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly array $transaction = [],
        public readonly array $callbackData = [],
    ) {}
}
