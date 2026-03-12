<?php

namespace Sarkhanrasimoghlu\KapitalBank\Enums;

enum ApiErrorCode: string
{
    case InvalidRequest = 'invalid_request';
    case InvalidCredentials = 'invalid_credentials';
    case InvalidToken = 'invalid_token';
    case TokenExpired = 'token_expired';
    case PaymentNotFound = 'payment_not_found';
    case PaymentAlreadyCanceled = 'payment_already_canceled';
    case PaymentAlreadyCaptured = 'payment_already_captured';
    case RefundNotFound = 'refund_not_found';
    case RefundAmountExceeded = 'refund_amount_exceeded';
    case InternalError = 'internal_error';
    case ServiceUnavailable = 'service_unavailable';
}
