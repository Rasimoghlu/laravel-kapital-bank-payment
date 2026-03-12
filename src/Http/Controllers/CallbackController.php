<?php

namespace Sarkhanrasimoghlu\KapitalBank\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sarkhanrasimoghlu\KapitalBank\Contracts\KapitalBankServiceInterface;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;
use Sarkhanrasimoghlu\KapitalBank\Enums\WebhookEvent;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentFailed;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentSucceeded;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\CallbackException;

class CallbackController extends Controller
{
    public function handle(Request $request, KapitalBankServiceInterface $service): JsonResponse
    {
        $event = $request->input('event');
        $paymentId = $request->input('payload.id');

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

        // Server-side verification: don't trust webhook payload, verify via API
        $verifiedStatus = null;
        try {
            $apiStatus = $service->getPaymentStatus($paymentId);
            $verifiedStatus = $apiStatus->status;

            Log::info('Webhook server-side verification', [
                'payment_id' => $paymentId,
                'webhook_event' => $event,
                'api_status' => $verifiedStatus->value,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Webhook server-side verification failed, falling back to webhook payload', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        $transactionStatus = $verifiedStatus
            ?? TransactionStatus::tryFrom($request->input('payload.status') ?? '')
            ?? TransactionStatus::Canceled;

        DB::table('kapital_bank_transactions')
            ->where('transaction_id', $paymentId)
            ->update([
                'status' => $transactionStatus->value,
                'paid_at' => $transactionStatus === TransactionStatus::Succeeded ? now() : null,
                'raw_response' => json_encode($request->all()),
                'updated_at' => now(),
            ]);

        if ($webhookEvent === WebhookEvent::PaymentSucceeded && $transactionStatus === TransactionStatus::Succeeded) {
            event(new PaymentSucceeded($paymentId, (array) $transaction, $request->all()));
        } else {
            event(new PaymentFailed($paymentId, (array) $transaction, $request->all()));
        }

        return response()->json(['status' => 'ok']);
    }
}
