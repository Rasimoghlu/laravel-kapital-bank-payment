<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

class InvalidConfigurationException extends KapitalBankException
{
    public static function missingKey(string $key): self
    {
        return new self(
            message: "Missing required configuration key: {$key}",
            context: ['key' => $key],
        );
    }

    public static function invalidUrl(string $key, string $url): self
    {
        return new self(
            message: "Configuration key '{$key}' must be a valid HTTPS URL, got: {$url}",
            context: ['key' => $key, 'url' => $url],
        );
    }
}
