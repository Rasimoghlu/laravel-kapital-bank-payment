<?php

namespace Sarkhanrasimoghlu\KapitalBank\DataTransferObjects;

use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;

final readonly class OrderItem
{
    public float $amount;

    public function __construct(
        public string $name,
        public float $price,
        public int $quantity,
    ) {
        if (empty($this->name)) {
            throw InvalidPaymentException::invalidOrderItem('Item name is required');
        }

        if ($this->price <= 0) {
            throw InvalidPaymentException::invalidOrderItem("Item price must be greater than zero, got: {$this->price}");
        }

        if ($this->quantity <= 0) {
            throw InvalidPaymentException::invalidOrderItem("Item quantity must be greater than zero, got: {$this->quantity}");
        }

        $this->amount = round($this->price * $this->quantity, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }
}
