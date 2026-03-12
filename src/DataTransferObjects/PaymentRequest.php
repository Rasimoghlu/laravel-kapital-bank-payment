<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\Currency;
use Sarkhanrasimoghlu\KapitalBank\Enums\Language;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class PaymentRequest
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        public float $amount,
        public Currency $currency,
        public string $orderId,
        public string $description = '',
        public bool $capture = true,
        public string $paymentMethodType = 'BANK_CARD',
        public string $confirmationType = 'REDIRECT',
        public string $returnUrl = '',
        public array $metadata = [],
        public Language $language = Language::AZ,
    ) {
        if ($this->amount <= 0) {
            throw InvalidPaymentException::invalidAmount($this->amount);
        }

        if (empty($this->orderId)) {
            throw InvalidPaymentException::missingOrderId();
        }
    }
}
