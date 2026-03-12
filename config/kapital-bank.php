<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merchant Credentials
    |--------------------------------------------------------------------------
    */

    'merchant_id' => env('KAPITAL_BANK_MERCHANT_ID'),

    'terminal_id' => env('KAPITAL_BANK_TERMINAL_ID'),

    'secret_key' => env('KAPITAL_BANK_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Credentials
    |--------------------------------------------------------------------------
    */

    'client_id' => env('KAPITAL_BANK_CLIENT_ID'),

    'client_secret' => env('KAPITAL_BANK_CLIENT_SECRET'),

    'token_cache_ttl' => env('KAPITAL_BANK_TOKEN_CACHE_TTL', 3500),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('KAPITAL_BANK_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    */

    'success_url' => env('KAPITAL_BANK_SUCCESS_URL'),

    'error_url' => env('KAPITAL_BANK_ERROR_URL'),

    'callback_url' => env('KAPITAL_BANK_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency & Language
    |--------------------------------------------------------------------------
    */

    'currency' => env('KAPITAL_BANK_CURRENCY', 'AZN'),

    'language' => env('KAPITAL_BANK_LANGUAGE', 'az'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    */

    'timeout' => env('KAPITAL_BANK_TIMEOUT', 30),

    'ssl_verify' => env('KAPITAL_BANK_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('KAPITAL_BANK_LOG_CHANNEL', 'stack'),
        'level' => env('KAPITAL_BANK_LOG_LEVEL', 'info'),
    ],

];
