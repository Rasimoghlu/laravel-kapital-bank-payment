<?php

namespace Sarkhanrasimoghlu\KapitalBank\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\TokenManagerInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\HttpException;

class GuzzleHttpClient implements HttpClientInterface
{
    private readonly Client $client;

    private ?TokenManagerInterface $tokenManager = null;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client([
            'timeout' => $this->configuration->getTimeout(),
            'verify' => $this->configuration->getSslVerify(),
        ]);
    }

    public function setTokenManager(TokenManagerInterface $tokenManager): void
    {
        $this->tokenManager = $tokenManager;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        $headers = $this->withAuthHeaders($headers);
        $headers = $this->withIdempotencyKey($headers);

        return $this->sendJsonRequest('POST', $url, $data, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers = []): array
    {
        $headers = $this->withAuthHeaders($headers);

        return $this->sendRequest('GET', $url, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function put(string $url, array $data = [], array $headers = []): array
    {
        $headers = $this->withAuthHeaders($headers);
        $headers = $this->withIdempotencyKey($headers);

        return $this->sendJsonRequest('PUT', $url, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'form_params' => $data,
                'headers' => array_merge([
                    'Accept' => 'application/json',
                ], $headers),
            ]);

            return $this->decodeJsonResponse($response->getBody()->getContents());
        } catch (ConnectException $e) {
            throw HttpException::connectionFailed($url, $e);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();

                throw HttpException::serverError($statusCode, $body);
            }

            throw HttpException::connectionFailed($url, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function sendJsonRequest(string $method, string $url, array $data, array $headers, bool $isRetry = false): array
    {
        try {
            $response = $this->client->request($method, $url, [
                'json' => $data,
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], $headers),
            ]);

            return $this->decodeJsonResponse($response->getBody()->getContents());
        } catch (ConnectException $e) {
            throw HttpException::connectionFailed($url, $e);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 401 && ! $isRetry && $this->tokenManager !== null) {
                $this->tokenManager->forceRefresh();
                $headers['Authorization'] = 'Bearer ' . $this->tokenManager->getToken();

                return $this->sendJsonRequest($method, $url, $data, $headers, true);
            }

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();

                throw HttpException::serverError($statusCode, $body);
            }

            throw HttpException::connectionFailed($url, $e);
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $url, array $headers, bool $isRetry = false): array
    {
        try {
            $response = $this->client->request($method, $url, [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                ], $headers),
            ]);

            return $this->decodeJsonResponse($response->getBody()->getContents());
        } catch (ConnectException $e) {
            throw HttpException::connectionFailed($url, $e);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 401 && ! $isRetry && $this->tokenManager !== null) {
                $this->tokenManager->forceRefresh();
                $headers['Authorization'] = 'Bearer ' . $this->tokenManager->getToken();

                return $this->sendRequest($method, $url, $headers, true);
            }

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();

                throw HttpException::serverError($statusCode, $body);
            }

            throw HttpException::connectionFailed($url, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw HttpException::serverError(0, 'Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function withAuthHeaders(array $headers): array
    {
        if ($this->tokenManager !== null && ! isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . $this->tokenManager->getToken();
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function withIdempotencyKey(array $headers): array
    {
        if (! isset($headers['X-Idempotency-Key'])) {
            $headers['X-Idempotency-Key'] = Str::uuid()->toString();
        }

        return $headers;
    }
}
