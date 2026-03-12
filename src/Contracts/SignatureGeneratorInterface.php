<?php

namespace Sarkhanrasimoghlu\KapitalBank\Contracts;

interface SignatureGeneratorInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function generate(array $data): string;

    /**
     * @param array<string, mixed> $data
     */
    public function verify(string $signature, array $data): bool;

    public function generateFromRawBody(string $rawBody): string;

    public function verifyRawBody(string $rawBody, string $signature): bool;
}
