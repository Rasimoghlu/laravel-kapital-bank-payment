<?php

namespace Sarkhanrasimoghlu\KapitalBank\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;
use Sarkhanrasimoghlu\KapitalBank\Enums\WebhookEvent;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentFailed;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentSucceeded;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\CallbackException;

class CallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $paymentId = $request->input('payload.id');
        $status = $request->input('payload.status');

        if (empty($event) || empty($paymentId)) {
            throw CallbackException::invalidPayload('Missing event or payload.id');
        }

        $webhookEvent = WebhookEvent::tryFrom($event);

        if ($webhookEvent === null) {
            throw CallbackException::invalidPayload("Unknown event: {$event}");
        }

        $transaction = DB::table('kapital_bank_transactions')
            ->where('transaction_id', $paymentId)
            ->first();

        if (! $transaction) {
            throw CallbackException::transactionNotFound($paymentId);
        }

        if (in_array($transaction->status, [
            TransactionStatus::Succeeded->value,
            TransactionStatus::Canceled->value,
        ])) {
            throw CallbackException::duplicateCallback($paymentId);
        }

        $transactionStatus = TransactionStatus::tryFrom($status ?? '') ?? TransactionStatus::Canceled;

        DB::table('kapital_bank_transactions')
            ->where('transaction_id', $paymentId)
            ->update([
                'status' => $transactionStatus->value,
                'paid_at' => $transactionStatus === TransactionStatus::Succeeded ? now() : null,
                'raw_response' => json_encode($request->all()),
                'updated_at' => now(),
            ]);

        if ($webhookEvent === WebhookEvent::PaymentSucceeded) {
            event(new PaymentSucceeded($paymentId, (array) $transaction, $request->all()));
        } else {
            event(new PaymentFailed($paymentId, (array) $transaction, $request->all()));
        }

        return response()->json(['status' => 'ok']);
    }
}
