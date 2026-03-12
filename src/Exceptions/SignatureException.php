<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

class SignatureException extends KapitalBankException
{
    public static function generationFailed(string $reason): self
    {
        return new self(
            message: "Signature generation failed: {$reason}",
            context: ['reason' => $reason],
        );
    }

    public static function verificationFailed(): self
    {
        return new self(message: 'Signature verification failed: invalid signature');
    }

    public static function expired(int $timestamp): self
    {
        return new self(
            message: 'Signature verification failed: request has expired',
            context: ['timestamp' => $timestamp],
        );
    }
}
