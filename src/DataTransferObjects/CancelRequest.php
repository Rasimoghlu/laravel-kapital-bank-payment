<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\CancellationReason;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class CancelRequest
{
    public function __construct(
        public string $paymentId,
        public ?CancellationReason $reason = null,
    ) {
        if (empty($this->paymentId)) {
            throw InvalidPaymentException::missingPaymentId();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->reason !== null) {
            $data['cancellation_reason'] = $this->reason->value;
        }

        return $data;
    }
}
