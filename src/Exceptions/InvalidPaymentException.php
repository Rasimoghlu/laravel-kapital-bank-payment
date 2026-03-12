<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

class InvalidPaymentException extends KapitalBankException
{
    public static function invalidAmount(float $amount): self
    {
        return new self(
            message: "Payment amount must be greater than zero, got: {$amount}",
            context: ['amount' => $amount],
        );
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(
            message: "Invalid currency: {$currency}",
            context: ['currency' => $currency],
        );
    }

    public static function missingOrderId(): self
    {
        return new self(message: 'Order ID is required');
    }

    public static function missingPaymentId(): self
    {
        return new self(message: 'Payment ID is required');
    }

    public static function invalidOrderItem(string $reason): self
    {
        return new self(
            message: "Invalid order item: {$reason}",
            context: ['reason' => $reason],
        );
    }
}
