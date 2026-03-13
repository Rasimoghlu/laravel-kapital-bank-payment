<?php

namespace Sarkhanrasimoghlu\KapitalBank\Listeners;

use Illuminate\Support\Facades\DB;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentCreated;

class SaveTransactionListener
{
    public function handle(PaymentCreated $event): void
    {
        DB::table('kapital_bank_transactions')->insertOrIgnore([
            'transaction_id' => $event->transactionId,
            'order_id' => $event->orderId,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'status' => $event->status,
            'payment_method' => $event->requestData['paymentMethodData']['type'] ?? null,
            'payment_url' => $event->paymentUrl,
            'raw_request' => json_encode($event->requestData),
            'raw_response' => json_encode($event->paymentData),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
