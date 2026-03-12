<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\Configuration\KapitalBankConfiguration;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidConfigurationException;

class KapitalBankConfigurationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validConfig(): array
    {
        return [
            'merchant_id' => 'test-merchant-id',
            'terminal_id' => 'test-terminal-id',
            'secret_key' => 'test-secret-key',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'token_cache_ttl' => 3500,
            'base_url' => 'https://api.kapitalbank.test',
            'success_url' => 'https://example.com/success',
            'error_url' => 'https://example.com/error',
            'callback_url' => 'https://example.com/callback',
            'currency' => 'AZN',
            'language' => 'az',
            'timeout' => 30,
            'ssl_verify' => true,
            'logging' => [
                'channel' => 'stack',
                'level' => 'info',
            ],
        ];
    }

    #[Test]
    public function it_creates_configuration_from_valid_array(): void
    {
        $config = KapitalBankConfiguration::fromArray($this->validConfig());

        $this->assertSame('test-merchant-id', $config->getMerchantId());
        $this->assertSame('test-terminal-id', $config->getTerminalId());
        $this->assertSame('test-secret-key', $config->getSecretKey());
        $this->assertSame('test-client-id', $config->getClientId());
        $this->assertSame('test-client-secret', $config->getClientSecret());
        $this->assertSame(3500, $config->getTokenCacheTtl());
        $this->assertSame('https://api.kapitalbank.test', $config->getBaseUrl());
        $this->assertSame('https://example.com/success', $config->getSuccessUrl());
        $this->assertSame('https://example.com/error', $config->getErrorUrl());
        $this->assertSame('https://example.com/callback', $config->getCallbackUrl());
        $this->assertSame('AZN', $config->getCurrency());
        $this->assertSame('az', $config->getLanguage());
        $this->assertSame(30, $config->getTimeout());
        $this->assertTrue($config->getSslVerify());
        $this->assertSame('stack', $config->getLogChannel());
        $this->assertSame('info', $config->getLogLevel());
    }

    #[Test]
    public function it_uses_default_values_for_optional_fields(): void
    {
        $config = KapitalBankConfiguration::fromArray([
            'merchant_id' => 'test',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'base_url' => 'https://api.test',
        ]);

        $this->assertSame('AZN', $config->getCurrency());
        $this->assertSame('az', $config->getLanguage());
        $this->assertSame(30, $config->getTimeout());
        $this->assertTrue($config->getSslVerify());
        $this->assertSame('stack', $config->getLogChannel());
        $this->assertSame('info', $config->getLogLevel());
        $this->assertSame(3500, $config->getTokenCacheTtl());
        $this->assertSame('', $config->getSecretKey());
    }

    #[Test]
    public function it_throws_exception_when_merchant_id_is_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration key: merchantId');

        $config = $this->validConfig();
        unset($config['merchant_id']);
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_throws_exception_when_client_id_is_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration key: clientId');

        $config = $this->validConfig();
        unset($config['client_id']);
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_throws_exception_when_client_secret_is_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration key: clientSecret');

        $config = $this->validConfig();
        unset($config['client_secret']);
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_throws_exception_when_base_url_is_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required configuration key: baseUrl');

        $config = $this->validConfig();
        unset($config['base_url']);
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_does_not_throw_exception_when_secret_key_is_missing(): void
    {
        $config = $this->validConfig();
        unset($config['secret_key']);

        $configuration = KapitalBankConfiguration::fromArray($config);

        $this->assertSame('', $configuration->getSecretKey());
    }

    #[Test]
    public function it_throws_exception_when_url_is_not_https(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("must be a valid HTTPS URL");

        $config = $this->validConfig();
        $config['base_url'] = 'http://api.kapitalbank.test';
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_throws_exception_when_success_url_is_not_https(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("must be a valid HTTPS URL");

        $config = $this->validConfig();
        $config['success_url'] = 'http://example.com/success';
        KapitalBankConfiguration::fromArray($config);
    }

    #[Test]
    public function it_allows_empty_optional_urls(): void
    {
        $config = $this->validConfig();
        $config['success_url'] = '';
        $config['error_url'] = '';
        $config['callback_url'] = '';

        $configuration = KapitalBankConfiguration::fromArray($config);

        $this->assertSame('', $configuration->getSuccessUrl());
        $this->assertSame('', $configuration->getErrorUrl());
        $this->assertSame('', $configuration->getCallbackUrl());
    }

    #[Test]
    public function it_returns_correct_context_on_missing_key_exception(): void
    {
        try {
            $config = $this->validConfig();
            unset($config['merchant_id']);
            KapitalBankConfiguration::fromArray($config);
            $this->fail('Expected exception was not thrown');
        } catch (InvalidConfigurationException $e) {
            $this->assertSame(['key' => 'merchantId'], $e->getContext());
        }
    }
}
