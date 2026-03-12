<?php

namespace Sarkhanrasimoghlu\KapitalBank\Enums;

enum WebhookEvent: string
{
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentCanceled = 'payment_canceled';
}
