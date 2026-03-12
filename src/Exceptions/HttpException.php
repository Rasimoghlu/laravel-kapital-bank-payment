<?php

namespace Sarkhanrasimoghlu\KapitalBank\Exceptions;

use Throwable;

class HttpException extends KapitalBankException
{
    public static function connectionFailed(string $url, ?Throwable $previous = null): self
    {
        return new self(
            message: "Connection to {$url} failed",
            previous: $previous,
            context: ['url' => $url],
        );
    }

    public static function timeout(string $url, int $timeout): self
    {
        return new self(
            message: "Request to {$url} timed out after {$timeout} seconds",
            context: ['url' => $url, 'timeout' => $timeout],
        );
    }

    public static function serverError(int $statusCode, string $body): self
    {
        return new self(
            message: "HTTP request failed with status code {$statusCode}",
            code: $statusCode,
            context: ['status_code' => $statusCode, 'body' => $body],
        );
    }
}
