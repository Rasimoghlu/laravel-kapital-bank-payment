<?php

namespace Sarkhanrasimoghlu\KapitalBank\Contracts;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function post(string $url, array $data = [], array $headers = []): array;

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers = []): array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function put(string $url, array $data = [], array $headers = []): array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $data = [], array $headers = []): array;
}
