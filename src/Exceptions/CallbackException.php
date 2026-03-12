<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

class CallbackException extends KapitalBankException
{
    public static function invalidPayload(string $reason): self
    {
        return new self(
            message: "Invalid callback payload: {$reason}",
            context: ['reason' => $reason],
        );
    }

    public static function duplicateCallback(string $transactionId): self
    {
        return new self(
            message: "Duplicate callback received for transaction: {$transactionId}",
            context: ['transaction_id' => $transactionId],
        );
    }

    public static function transactionNotFound(string $transactionId): self
    {
        return new self(
            message: "Transaction not found: {$transactionId}",
            context: ['transaction_id' => $transactionId],
        );
    }
}
