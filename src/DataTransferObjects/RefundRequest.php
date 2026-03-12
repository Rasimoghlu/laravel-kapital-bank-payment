<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class RefundRequest
{
    public function __construct(
        public string $paymentId,
        public ?float $amount = null,
    ) {
        if (empty($this->paymentId)) {
            throw InvalidPaymentException::missingPaymentId();
        }

        if ($this->amount !== null && $this->amount <= 0) {
            throw InvalidPaymentException::invalidAmount($this->amount);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['payment_id' => $this->paymentId];

        if ($this->amount !== null) {
            $data['amount'] = $this->amount;
        }

        return $data;
    }
}
