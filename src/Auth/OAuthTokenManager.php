<?php

namespace Sarkhanrasimoghlu\KapitalBank\Auth;

use Illuminate\Support\Facades\Cache;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\TokenManagerInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\AuthenticationException;

class OAuthTokenManager implements TokenManagerInterface
{
    private const string CACHE_KEY = 'kapital_bank_oauth_token';

    private const int LOCK_TIMEOUT = 10;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getToken(): string
    {
        $token = Cache::get(self::CACHE_KEY);

        if ($token !== null) {
            return $token;
        }

        return $this->fetchAndCacheToken();
    }

    public function forceRefresh(): string
    {
        $this->invalidate();

        return $this->fetchAndCacheToken();
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function fetchAndCacheToken(): string
    {
        $lock = Cache::lock(self::CACHE_KEY . ':lock', self::LOCK_TIMEOUT);

        try {
            return $lock->block(self::LOCK_TIMEOUT, function () {
                $cached = Cache::get(self::CACHE_KEY);

                if ($cached !== null) {
                    return $cached;
                }

                $token = $this->requestToken();
                $ttl = $this->configuration->getTokenCacheTtl();

                Cache::put(self::CACHE_KEY, $token, $ttl);

                return $token;
            });
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw AuthenticationException::tokenFetchFailed($e->getMessage());
        }
    }

    private function requestToken(): string
    {
        $clientId = $this->configuration->getClientId();
        $clientSecret = $this->configuration->getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            throw AuthenticationException::missingCredentials();
        }

        $url = rtrim($this->configuration->getBaseUrl(), '/') . '/api/oauth2/token';

        try {
            $response = $this->httpClient->postForm($url, [
                'grant_type' => 'client_credentials',
                'scope' => 'email',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        } catch (\Throwable $e) {
            throw AuthenticationException::tokenFetchFailed($e->getMessage());
        }

        $accessToken = $response['accessToken'] ?? $response['access_token'] ?? '';

        if (empty($accessToken)) {
            throw AuthenticationException::tokenFetchFailed('No access_token in response');
        }

        return $accessToken;
    }
}
