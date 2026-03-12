<?php

namespace Sarkhanrasimoghlu\KapitalBank\Contracts;

interface TokenManagerInterface
{
    public function getToken(): string;

    public function forceRefresh(): string;

    public function invalidate(): void;
}
