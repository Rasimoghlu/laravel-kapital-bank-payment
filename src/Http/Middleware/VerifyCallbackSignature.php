<?php

namespace Sarkhanrasimoghlu\KapitalBank\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sarkhanrasimoghlu\KapitalBank\Contracts\SignatureGeneratorInterface;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\SignatureException;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackSignature
{
    public function __construct(
        private readonly SignatureGeneratorInterface $signatureGenerator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature', '');

        if (empty($signature)) {
            throw SignatureException::verificationFailed();
        }

        $rawBody = $request->getContent();

        if (! $this->signatureGenerator->verifyRawBody($rawBody, $signature)) {
            throw SignatureException::verificationFailed();
        }

        return $next($request);
    }
}
