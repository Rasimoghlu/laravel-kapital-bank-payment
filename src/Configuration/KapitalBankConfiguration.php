<?php

namespace Sarkhanrasimoghlu\KapitalBank\Configuration;

use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidConfigurationException;

readonly class KapitalBankConfiguration implements ConfigurationInterface
{
    public function __construct(
        public string $merchantId,
        public string $terminalId,
        public string $secretKey,
        public string $clientId,
        public string $clientSecret,
        public int    $tokenCacheTtl,
        public string $baseUrl,
        public string $successUrl,
        public string $errorUrl,
        public string $callbackUrl,
        public string $currency,
        public string $language,
        public int    $timeout,
        public bool   $sslVerify,
        public string $logChannel,
        public string $logLevel,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            merchantId: (string) ($config['merchant_id'] ?? ''),
            terminalId: (string) ($config['terminal_id'] ?? ''),
            secretKey: (string) ($config['secret_key'] ?? ''),
            clientId: (string) ($config['client_id'] ?? ''),
            clientSecret: (string) ($config['client_secret'] ?? ''),
            tokenCacheTtl: (int) ($config['token_cache_ttl'] ?? 3500),
            baseUrl: (string) ($config['base_url'] ?? ''),
            successUrl: (string) ($config['success_url'] ?? ''),
            errorUrl: (string) ($config['error_url'] ?? ''),
            callbackUrl: (string) ($config['callback_url'] ?? ''),
            currency: (string) ($config['currency'] ?? 'AZN'),
            language: (string) ($config['language'] ?? 'az'),
            timeout: (int) ($config['timeout'] ?? 30),
            sslVerify: (bool) ($config['ssl_verify'] ?? true),
            logChannel: (string) ($config['logging']['channel'] ?? 'stack'),
            logLevel: (string) ($config['logging']['level'] ?? 'info'),
        );
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getTokenCacheTtl(): int
    {
        return $this->tokenCacheTtl;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    public function getErrorUrl(): string
    {
        return $this->errorUrl;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getSslVerify(): bool
    {
        return $this->sslVerify;
    }

    public function getLogChannel(): string
    {
        return $this->logChannel;
    }

    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    public function validate(): void
    {
        $requiredKeys = [
            'merchantId' => $this->merchantId,
            'baseUrl' => $this->baseUrl,
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ];

        foreach ($requiredKeys as $key => $value) {
            if (empty($value)) {
                throw InvalidConfigurationException::missingKey($key);
            }
        }

        $urlKeys = [
            'baseUrl' => $this->baseUrl,
            'successUrl' => $this->successUrl,
            'errorUrl' => $this->errorUrl,
            'callbackUrl' => $this->callbackUrl,
        ];

        $isLocal = in_array(env('APP_ENV'), ['local', 'testing'], true);

        foreach ($urlKeys as $key => $url) {
            if (! empty($url) && ! str_starts_with($url, 'https://') && ! $isLocal) {
                throw InvalidConfigurationException::invalidUrl($key, $url);
            }
        }
    }
}
