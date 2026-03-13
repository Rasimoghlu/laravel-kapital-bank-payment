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

    public static function invalidPaymentMethod(string $method): self
    {
        return new self(
            message: "Invalid payment method type: {$method}. Allowed: BANK_CARD, BIRBANK, M10",
            context: ['payment_method' => $method],
        );
    }

    public static function invalidConfirmationType(string $type): self
    {
        return new self(
            message: "Invalid confirmation type: {$type}. Allowed: REDIRECT, QR, MOBILE",
            context: ['confirmation_type' => $type],
        );
    }
}
