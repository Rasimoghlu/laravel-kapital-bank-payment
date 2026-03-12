<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class CancelRequest
{
    public function __construct(
        public string $paymentId,
    ) {
        if (empty($this->paymentId)) {
            throw InvalidPaymentException::missingPaymentId();
        }
    }
}
