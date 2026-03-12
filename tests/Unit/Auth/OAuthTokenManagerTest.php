<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\Auth;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sarkhanrasimoghlu\KapitalBank\Auth\OAuthTokenManager;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\AuthenticationException;
use Sarkhanrasimoghlu\KapitalBank\KapitalBankServiceProvider;

class OAuthTokenManagerTest extends TestCase
{
    private ConfigurationInterface $configuration;

    private HttpClientInterface $httpClient;

    private OAuthTokenManager $tokenManager;

    protected function getPackageProviders($app): array
    {
        return [KapitalBankServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('kapital-bank.merchant_id', 'test-merchant');
        $app['config']->set('kapital-bank.client_id', 'test-client-id');
        $app['config']->set('kapital-bank.client_secret', 'test-client-secret');
        $app['config']->set('kapital-bank.base_url', 'https://api.kapitalbank.test');
        $app['config']->set('kapital-bank.terminal_id', 'test-terminal');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->configuration->method('getBaseUrl')->willReturn('https://api.kapitalbank.test');
        $this->configuration->method('getClientId')->willReturn('test-client-id');
        $this->configuration->method('getClientSecret')->willReturn('test-client-secret');
        $this->configuration->method('getTokenCacheTtl')->willReturn(3500);

        $this->tokenManager = new OAuthTokenManager(
            $this->configuration,
            $this->httpClient,
        );

        Cache::flush();
    }

    #[Test]
    public function it_fetches_token_from_api(): void
    {
        $this->httpClient->method('postForm')->willReturn([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $token = $this->tokenManager->getToken();

        $this->assertSame('test-access-token', $token);
    }

    #[Test]
    public function it_returns_cached_token(): void
    {
        Cache::put('kapital_bank_oauth_token', 'cached-token', 3500);

        $this->httpClient->expects($this->never())->method('postForm');

        $token = $this->tokenManager->getToken();

        $this->assertSame('cached-token', $token);
    }

    #[Test]
    public function it_force_refreshes_token(): void
    {
        Cache::put('kapital_bank_oauth_token', 'old-token', 3500);

        $this->httpClient->method('postForm')->willReturn([
            'access_token' => 'new-access-token',
        ]);

        $token = $this->tokenManager->forceRefresh();

        $this->assertSame('new-access-token', $token);
    }

    #[Test]
    public function it_invalidates_cached_token(): void
    {
        Cache::put('kapital_bank_oauth_token', 'cached-token', 3500);

        $this->tokenManager->invalidate();

        $this->assertNull(Cache::get('kapital_bank_oauth_token'));
    }

    #[Test]
    public function it_throws_exception_when_credentials_are_missing(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getBaseUrl')->willReturn('https://api.kapitalbank.test');
        $config->method('getClientId')->willReturn('');
        $config->method('getClientSecret')->willReturn('');
        $config->method('getTokenCacheTtl')->willReturn(3500);

        $manager = new OAuthTokenManager($config, $this->httpClient);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('client_id and client_secret are required');

        $manager->getToken();
    }

    #[Test]
    public function it_throws_exception_when_response_has_no_access_token(): void
    {
        $this->httpClient->method('postForm')->willReturn([
            'error' => 'invalid_client',
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No access_token in response');

        $this->tokenManager->getToken();
    }

    #[Test]
    public function it_throws_exception_when_api_call_fails(): void
    {
        $this->httpClient->method('postForm')->willThrowException(
            new \RuntimeException('Connection refused')
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to fetch OAuth2 token');

        $this->tokenManager->getToken();
    }
}
