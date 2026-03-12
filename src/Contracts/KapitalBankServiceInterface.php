<?php

namespace Sarkhanrasimoghlu\KapitalBank\Contracts;

use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelResponse;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentResponse;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentStatus;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundResponse;

interface KapitalBankServiceInterface
{
    public function createPayment(PaymentRequest $request): PaymentResponse;

    public function getPaymentStatus(string $paymentId): PaymentStatus;

    public function cancelPayment(CancelRequest $request): CancelResponse;

    public function refund(RefundRequest $request): RefundResponse;

    public function getRefundStatus(string $refundId): RefundResponse;
}
