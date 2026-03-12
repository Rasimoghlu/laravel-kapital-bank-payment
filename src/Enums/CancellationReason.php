<?php

namespace Sarkhanrasimoghlu\KapitalBank\Enums;

enum CancellationReason: string
{
    case CanceledByMerchant = 'canceled_by_merchant';
    case CanceledByPaymentNetwork = 'canceled_by_payment_network';
    case ExpiredOnConfirmation = 'expired_on_confirmation';
    case InsufficientFunds = 'insufficient_funds';
    case ThreeDsVerificationFailed = 'three_ds_verification_failed';
    case ExpiredOnCapture = 'expired_on_capture';
    case IssuerDecline = 'issuer_decline';
    case GeneralDecline = 'general_decline';
}
