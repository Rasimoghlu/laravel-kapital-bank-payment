<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sarkhanrasimoghlu\KapitalBank\Contracts\SignatureGeneratorInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\SignatureException;
use Sarkhanrasimoghlu\KapitalBank\Http\Middleware\VerifyCallbackSignature;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackSignatureTest extends TestCase
{
    private SignatureGeneratorInterface $signatureGenerator;

    private VerifyCallbackSignature $middleware;

    protected function setUp(): void
    {
        $this->signatureGenerator = $this->createMock(SignatureGeneratorInterface::class);
        $this->middleware = new VerifyCallbackSignature($this->signatureGenerator);
    }

    #[Test]
    public function it_passes_with_valid_signature_in_header(): void
    {
        $this->signatureGenerator->method('verifyRawBody')->willReturn(true);

        $request = Request::create(
            '/kapital-bank/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"event":"payment_succeeded","payload":{"id":"pay_001","status":"succeeded"}}',
        );
        $request->headers->set('X-Signature', 'valid-base64-signature');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertSame('OK', $response->getContent());
    }

    #[Test]
    public function it_throws_exception_when_signature_header_is_missing(): void
    {
        $request = Request::create(
            '/kapital-bank/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"event":"payment_succeeded"}',
        );

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $this->middleware->handle($request, function () {
            return new Response('OK');
        });
    }

    #[Test]
    public function it_throws_exception_when_signature_is_invalid(): void
    {
        $this->signatureGenerator->method('verifyRawBody')->willReturn(false);

        $request = Request::create(
            '/kapital-bank/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"event":"payment_succeeded"}',
        );
        $request->headers->set('X-Signature', 'invalid-signature');

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $this->middleware->handle($request, function () {
            return new Response('OK');
        });
    }

    #[Test]
    public function it_verifies_using_raw_body(): void
    {
        $rawBody = '{"event":"payment_succeeded","payload":{"id":"pay_001"}}';

        $this->signatureGenerator->expects($this->once())
            ->method('verifyRawBody')
            ->with($rawBody, 'test-signature')
            ->willReturn(true);

        $request = Request::create(
            '/kapital-bank/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $rawBody,
        );
        $request->headers->set('X-Signature', 'test-signature');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertSame('OK', $response->getContent());
    }
}
