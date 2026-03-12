<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Enums\Currency;
use Sarkhanrasimoghlu\KapitalBank\Enums\Language;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class PaymentRequest
{
    /**
     * @param OrderItem[] $items
     */
    public function __construct(
        public float $amount,
        public Currency $currency,
        public string $orderId,
        public string $description = '',
        public array $items = [],
        public string $successUrl = '',
        public string $errorUrl = '',
        public Language $language = Language::AZ,
    ) {
        if ($this->amount <= 0) {
            throw InvalidPaymentException::invalidAmount($this->amount);
        }

        if (empty($this->orderId)) {
            throw InvalidPaymentException::missingOrderId();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'order_id' => $this->orderId,
            'description' => $this->description,
            'items' => array_map(fn (OrderItem $item) => $item->toArray(), $this->items),
            'success_url' => $this->successUrl,
            'error_url' => $this->errorUrl,
            'language' => $this->language->value,
        ];
    }
}
