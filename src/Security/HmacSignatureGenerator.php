<?php

namespace Sarkhanrasimoghlu\KapitalBank\Security;

use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\SignatureGeneratorInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\SignatureException;

class HmacSignatureGenerator implements SignatureGeneratorInterface
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function generate(array $data): string
    {
        $secretKey = $this->configuration->getSecretKey();

        if (empty($secretKey)) {
            throw SignatureException::generationFailed('Secret key is not configured');
        }

        ksort($data);

        $payload = $this->buildPayload($data);

        return hash_hmac('sha256', $payload, $secretKey);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function verify(string $signature, array $data): bool
    {
        $expectedSignature = $this->generate($data);

        return hash_equals($expectedSignature, $signature);
    }

    public function generateFromRawBody(string $rawBody): string
    {
        $secretKey = $this->configuration->getSecretKey();

        if (empty($secretKey)) {
            throw SignatureException::generationFailed('Secret key is not configured');
        }

        return base64_encode(hash_hmac('sha256', $rawBody, $secretKey, true));
    }

    public function verifyRawBody(string $rawBody, string $signature): bool
    {
        try {
            $expectedSignature = $this->generateFromRawBody($rawBody);
        } catch (SignatureException) {
            return false;
        }

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildPayload(array $data): string
    {
        $parts = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $parts[] = "{$key}={$value}";
        }

        return implode('&', $parts);
    }
}
