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
    private const VALID_PAYMENT_METHODS = ['BANK_CARD', 'BIRBANK', 'M10'];
    private const VALID_CONFIRMATION_TYPES = ['REDIRECT', 'QR', 'MOBILE'];

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

        if (!in_array($this->paymentMethodType, self::VALID_PAYMENT_METHODS, true)) {
            throw InvalidPaymentException::invalidPaymentMethod($this->paymentMethodType);
        }

        if (!in_array($this->confirmationType, self::VALID_CONFIRMATION_TYPES, true)) {
            throw InvalidPaymentException::invalidConfirmationType($this->confirmationType);
        }
    }
}
