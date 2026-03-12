<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

class AuthenticationException extends KapitalBankException
{
    public static function tokenFetchFailed(string $reason = ''): self
    {
        return new self(
            message: 'Failed to fetch OAuth2 token' . ($reason ? ": {$reason}" : ''),
            context: ['reason' => $reason],
        );
    }

    public static function tokenRefreshFailed(string $reason = ''): self
    {
        return new self(
            message: 'Failed to refresh OAuth2 token' . ($reason ? ": {$reason}" : ''),
            context: ['reason' => $reason],
        );
    }

    public static function missingCredentials(): self
    {
        return new self(
            message: 'OAuth2 client_id and client_secret are required',
        );
    }
}
