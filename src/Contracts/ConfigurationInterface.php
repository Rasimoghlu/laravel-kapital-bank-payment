<?php

namespace Sarkhanrasimoghlu\KapitalBank\Contracts;

interface ConfigurationInterface
{
    public function getMerchantId(): string;

    public function getTerminalId(): string;

    public function getSecretKey(): string;

    public function getClientId(): string;

    public function getClientSecret(): string;

    public function getTokenCacheTtl(): int;

    public function getBaseUrl(): string;

    public function getSuccessUrl(): string;

    public function getErrorUrl(): string;

    public function getCallbackUrl(): string;

    public function getCurrency(): string;

    public function getLanguage(): string;

    public function getTimeout(): int;

    public function getSslVerify(): bool;

    public function getLogChannel(): string;

    public function getLogLevel(): string;

    public function validate(): void;
}
