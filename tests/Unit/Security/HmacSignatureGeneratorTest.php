<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\SignatureException;
use Sarkhanrasimoghlu\KapitalBank\Security\HmacSignatureGenerator;

class HmacSignatureGeneratorTest extends TestCase
{
    private HmacSignatureGenerator $generator;

    private ConfigurationInterface $configuration;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->configuration->method('getSecretKey')->willReturn('test-secret-key');

        $this->generator = new HmacSignatureGenerator($this->configuration);
    }

    #[Test]
    public function it_generates_signature(): void
    {
        $data = ['amount' => 100, 'currency' => 'AZN', 'order_id' => 'ORDER-001'];

        $signature = $this->generator->generate($data);

        $this->assertNotEmpty($signature);
        $this->assertSame(64, strlen($signature));
    }

    #[Test]
    public function it_generates_consistent_signatures(): void
    {
        $data = ['amount' => 100, 'currency' => 'AZN'];

        $signature1 = $this->generator->generate($data);
        $signature2 = $this->generator->generate($data);

        $this->assertSame($signature1, $signature2);
    }

    #[Test]
    public function it_generates_sorted_signatures(): void
    {
        $data1 = ['b' => 2, 'a' => 1];
        $data2 = ['a' => 1, 'b' => 2];

        $signature1 = $this->generator->generate($data1);
        $signature2 = $this->generator->generate($data2);

        $this->assertSame($signature1, $signature2);
    }

    #[Test]
    public function it_generates_different_signatures_for_different_data(): void
    {
        $signature1 = $this->generator->generate(['amount' => 100]);
        $signature2 = $this->generator->generate(['amount' => 200]);

        $this->assertNotSame($signature1, $signature2);
    }

    #[Test]
    public function it_verifies_valid_signature(): void
    {
        $data = ['amount' => 100, 'currency' => 'AZN'];
        $signature = $this->generator->generate($data);

        $this->assertTrue($this->generator->verify($signature, $data));
    }

    #[Test]
    public function it_rejects_invalid_signature(): void
    {
        $data = ['amount' => 100, 'currency' => 'AZN'];

        $this->assertFalse($this->generator->verify('invalid-signature', $data));
    }

    #[Test]
    public function it_rejects_tampered_data(): void
    {
        $data = ['amount' => 100, 'currency' => 'AZN'];
        $signature = $this->generator->generate($data);

        $tamperedData = ['amount' => 200, 'currency' => 'AZN'];

        $this->assertFalse($this->generator->verify($signature, $tamperedData));
    }

    #[Test]
    public function it_throws_exception_when_secret_key_is_empty(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSecretKey')->willReturn('');

        $generator = new HmacSignatureGenerator($config);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Secret key is not configured');

        $generator->generate(['test' => 'data']);
    }

    #[Test]
    public function it_handles_array_values_in_data(): void
    {
        $data = [
            'amount' => 100,
            'items' => ['item1', 'item2'],
        ];

        $signature = $this->generator->generate($data);

        $this->assertNotEmpty($signature);
        $this->assertTrue($this->generator->verify($signature, $data));
    }

    #[Test]
    public function it_generates_base64_signature_from_raw_body(): void
    {
        $rawBody = '{"event":"payment_succeeded","payload":{"id":"pay_123","status":"succeeded"}}';

        $signature = $this->generator->generateFromRawBody($rawBody);

        $this->assertNotEmpty($signature);
        $decoded = base64_decode($signature, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(32, strlen($decoded)); // SHA-256 = 32 bytes
    }

    #[Test]
    public function it_verifies_raw_body_signature(): void
    {
        $rawBody = '{"event":"payment_succeeded","payload":{"id":"pay_123"}}';

        $signature = $this->generator->generateFromRawBody($rawBody);

        $this->assertTrue($this->generator->verifyRawBody($rawBody, $signature));
    }

    #[Test]
    public function it_rejects_invalid_raw_body_signature(): void
    {
        $rawBody = '{"event":"payment_succeeded"}';

        $this->assertFalse($this->generator->verifyRawBody($rawBody, 'invalid-signature'));
    }

    #[Test]
    public function it_rejects_tampered_raw_body(): void
    {
        $rawBody = '{"event":"payment_succeeded","payload":{"id":"pay_123"}}';
        $signature = $this->generator->generateFromRawBody($rawBody);

        $tamperedBody = '{"event":"payment_succeeded","payload":{"id":"pay_456"}}';

        $this->assertFalse($this->generator->verifyRawBody($tamperedBody, $signature));
    }

    #[Test]
    public function it_throws_exception_for_raw_body_when_secret_key_is_empty(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSecretKey')->willReturn('');

        $generator = new HmacSignatureGenerator($config);

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Secret key is not configured');

        $generator->generateFromRawBody('test body');
    }

    #[Test]
    public function it_returns_false_for_verify_raw_body_when_secret_key_is_empty(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSecretKey')->willReturn('');

        $generator = new HmacSignatureGenerator($config);

        $this->assertFalse($generator->verifyRawBody('test body', 'any-signature'));
    }
}
