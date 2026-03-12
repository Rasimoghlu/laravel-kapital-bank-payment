<?php

use Illuminate\Support\Facades\Route;
use Sarkhanrasimoghlu\KapitalBank\Http\Controllers\CallbackController;
use Sarkhanrasimoghlu\KapitalBank\Http\Middleware\VerifyCallbackSignature;

Route::post('/kapital-bank/callback', [CallbackController::class, 'handle'])
    ->middleware(VerifyCallbackSignature::class)
    ->name('kapital-bank.callback');
