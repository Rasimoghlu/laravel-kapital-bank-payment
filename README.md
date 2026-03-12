# Laravel Kapital Bank Payment

A Laravel package for integrating with the **Kapital Bank Checkout Merchant API V1.3**. Handles payments, refunds, cancellations, OAuth2 authentication, and webhook verification out of the box.

## Requirements

- PHP 8.3+
- Laravel 12.0+
- Guzzle 7.8+

## Installation

```bash
composer require sarkhanrasimoghlu/laravel-kapital-bank-payment
```

The service provider is auto-discovered. Publish the config and migration:

```bash
php artisan vendor:publish --tag=kapital-bank-config
php artisan vendor:publish --tag=kapital-bank-migrations
php artisan migrate
```

## Configuration

Add these to your `.env` file:

```env
KAPITAL_BANK_MERCHANT_ID=your-merchant-id
KAPITAL_BANK_TERMINAL_ID=your-terminal-id
KAPITAL_BANK_CLIENT_ID=your-oauth2-client-id
KAPITAL_BANK_CLIENT_SECRET=your-oauth2-client-secret
KAPITAL_BANK_SECRET_KEY=your-webhook-secret-key
KAPITAL_BANK_BASE_URL=https://e-commerce.kapitalbank.az
KAPITAL_BANK_SUCCESS_URL=https://yourapp.com/payment/success
KAPITAL_BANK_ERROR_URL=https://yourapp.com/payment/error
KAPITAL_BANK_CALLBACK_URL=https://yourapp.com/kapital-bank/callback
```

| Key | Required | Description |
|-----|----------|-------------|
| `MERCHANT_ID` | Yes | Merchant identifier |
| `TERMINAL_ID` | Yes | Terminal identifier |
| `CLIENT_ID` | Yes | OAuth2 client ID |
| `CLIENT_SECRET` | Yes | OAuth2 client secret |
| `SECRET_KEY` | No | Webhook HMAC secret (required only if using webhook verification) |
| `BASE_URL` | Yes | API base URL (must be HTTPS) |
| `SUCCESS_URL` | No | Redirect URL after successful payment |
| `ERROR_URL` | No | Redirect URL after failed payment |
| `CALLBACK_URL` | No | Webhook callback URL |

Optional settings:

```env
KAPITAL_BANK_CURRENCY=AZN          # Default currency (AZN, USD, EUR)
KAPITAL_BANK_LANGUAGE=az           # Default language (az, en, ru)
KAPITAL_BANK_TIMEOUT=30            # HTTP timeout in seconds
KAPITAL_BANK_SSL_VERIFY=true       # SSL certificate verification
KAPITAL_BANK_TOKEN_CACHE_TTL=3500  # OAuth token cache lifetime in seconds
KAPITAL_BANK_LOG_CHANNEL=stack     # Log channel
KAPITAL_BANK_LOG_LEVEL=info        # Log level
```

## Usage

### Create a Payment

```php
use Sarkhanrasimoghlu\KapitalBank\Contracts\KapitalBankServiceInterface;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentRequest;
use Sarkhanrasimoghlu\KapitalBank\Enums\Currency;
use Sarkhanrasimoghlu\KapitalBank\Enums\Language;

$service = app(KapitalBankServiceInterface::class);

$request = new PaymentRequest(
    amount: 49.99,
    currency: Currency::AZN,
    orderId: 'ORDER-12345',
    description: 'Premium subscription',
    language: Language::AZ,
);

$response = $service->createPayment($request);

// Redirect customer to payment page
return redirect($response->paymentUrl);

// $response->transactionId  — save this to track the payment
// $response->status         — TransactionStatus::Pending
// $response->rawResponse    — full API response
```

### Create a Payment with Line Items

```php
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\OrderItem;

$request = new PaymentRequest(
    amount: 75.00,
    currency: Currency::AZN,
    orderId: 'ORDER-12346',
    description: 'Online order',
    items: [
        new OrderItem(name: 'T-Shirt', price: 25.00, quantity: 2),
        new OrderItem(name: 'Shipping', price: 5.00, quantity: 1),
    ],
    successUrl: 'https://yourapp.com/order/12346/thank-you',
    errorUrl: 'https://yourapp.com/order/12346/failed',
);

$response = $service->createPayment($request);
```

### Get Payment Status

```php
$status = $service->getPaymentStatus('pay_abc123');

$status->paymentId;      // "pay_abc123"
$status->status;          // TransactionStatus::Succeeded
$status->amount;          // 49.99
$status->currency;        // "AZN"
$status->paymentMethod;   // PaymentMethod::Card
$status->paidAt;          // DateTimeImmutable or null
$status->rawResponse;     // full API response
```

### Cancel a Payment

```php
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelRequest;
use Sarkhanrasimoghlu\KapitalBank\Enums\CancellationReason;

$response = $service->cancelPayment(new CancelRequest(
    paymentId: 'pay_abc123',
    reason: CancellationReason::CanceledByMerchant,
));

$response->paymentId;  // "pay_abc123"
$response->status;      // TransactionStatus::Canceled
```

Available cancellation reasons:

| Enum Case | Value |
|-----------|-------|
| `CanceledByMerchant` | `canceled_by_merchant` |
| `CanceledByPaymentNetwork` | `canceled_by_payment_network` |
| `ExpiredOnConfirmation` | `expired_on_confirmation` |
| `InsufficientFunds` | `insufficient_funds` |
| `ThreeDsVerificationFailed` | `three_ds_verification_failed` |
| `ExpiredOnCapture` | `expired_on_capture` |
| `IssuerDecline` | `issuer_decline` |
| `GeneralDecline` | `general_decline` |

### Full Refund

```php
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;

$response = $service->refund(new RefundRequest(
    paymentId: 'pay_abc123',
));

$response->success;    // true
$response->refundId;   // "ref_xyz789"
$response->status;     // TransactionStatus::Succeeded
$response->message;    // "Full refund processed"
```

### Partial Refund

```php
$response = $service->refund(new RefundRequest(
    paymentId: 'pay_abc123',
    amount: 15.00,
));
```

### Get Refund Status

```php
$response = $service->getRefundStatus('ref_xyz789');

$response->refundId;   // "ref_xyz789"
$response->success;    // true
$response->status;     // TransactionStatus::Succeeded
```

## Webhooks

The package automatically registers a `POST /kapital-bank/callback` route protected by signature verification middleware.

### Webhook Payload Format

Kapital Bank sends webhooks in this format:

```json
{
    "event": "payment_succeeded",
    "payload": {
        "id": "pay_abc123",
        "type": "payment",
        "status": "succeeded",
        "paymentMethod": "card"
    }
}
```

Supported events: `payment_succeeded`, `payment_canceled`.

### Signature Verification

The middleware verifies the `X-Signature` header using Base64-encoded HMAC-SHA256 of the raw request body:

```
X-Signature: base64(HMAC-SHA256(raw_body, secret_key))
```

Requests without a valid signature are rejected.

### Listening to Events

The package dispatches Laravel events that you can listen to:

```php
// app/Providers/EventServiceProvider.php
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentCreated;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentSucceeded;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentFailed;

protected $listen = [
    PaymentCreated::class => [
        // Fired when createPayment() succeeds
        // $event->transactionId, $event->orderId, $event->amount
    ],
    PaymentSucceeded::class => [
        // Fired on payment_succeeded webhook
        // $event->transactionId, $event->transaction, $event->callbackData
    ],
    PaymentFailed::class => [
        // Fired on payment_canceled webhook
        // $event->transactionId, $event->transaction, $event->callbackData
    ],
];
```

Example listener:

```php
class HandlePaymentSuccess
{
    public function handle(PaymentSucceeded $event): void
    {
        $order = Order::where('payment_id', $event->transactionId)->first();
        $order->markAsPaid();
    }
}
```

## Authentication

The package uses **OAuth2 client credentials** flow. Tokens are automatically:

- Fetched on first API request
- Cached in Laravel's cache store (default TTL: 3500 seconds)
- Refreshed automatically on 401 responses (exactly once per request)
- Protected against thundering herd with cache locks

You never need to manage tokens manually.

## API Endpoints

| Method | Endpoint | Service Method |
|--------|----------|----------------|
| `POST` | `/oauth2/token` | Automatic |
| `POST` | `/v1/payments` | `createPayment()` |
| `GET` | `/v1/payments/{id}` | `getPaymentStatus()` |
| `PUT` | `/v1/payments/{id}/cancel` | `cancelPayment()` |
| `POST` | `/v1/refunds` | `refund()` |
| `GET` | `/v1/refunds/{id}` | `getRefundStatus()` |
| `POST` | `/kapital-bank/callback` | Webhook (auto-handled) |

## Transaction Statuses

| Enum Case | Value | Description |
|-----------|-------|-------------|
| `Pending` | `pending` | Payment created, awaiting customer action |
| `Succeeded` | `succeeded` | Payment completed successfully |
| `Canceled` | `canceled` | Payment was canceled |
| `WaitingForCapture` | `waiting_for_capture` | Authorized, awaiting capture |
| `Refunded` | `refunded` | Fully refunded |
| `PartiallyRefunded` | `partially_refunded` | Partially refunded |

## Error Handling

All exceptions extend `KapitalBankException` which carries a `getContext()` method with structured error details:

```php
use Sarkhanrasimoghlu\KapitalBank\Exceptions\KapitalBankException;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\HttpException;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\AuthenticationException;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\InvalidPaymentException;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\SignatureException;

try {
    $response = $service->createPayment($request);
} catch (AuthenticationException $e) {
    // OAuth2 token fetch/refresh failed
} catch (HttpException $e) {
    // API connection failed or returned error
    $statusCode = $e->getCode();
    $context = $e->getContext(); // ['status_code' => 500, 'body' => '...']
} catch (InvalidPaymentException $e) {
    // Validation error (invalid amount, missing order ID, etc.)
} catch (KapitalBankException $e) {
    // Catch-all for any package exception
}
```

## Security

- **OAuth2 Bearer Token** injected automatically on every API request
- **Token caching** with cache lock to prevent thundering herd
- **401 auto-retry** with token refresh (exactly once)
- **Base64 HMAC-SHA256** webhook signature verification on raw body
- **`hash_equals()`** for timing-safe signature comparison
- **`X-Idempotency-Key`** UUID on every POST/PUT to prevent double charges
- **HTTPS enforcement** on all configured URLs
- **`client_secret` never logged** in any context

## Database

The package creates a `kapital_bank_transactions` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `transaction_id` | string | Kapital Bank payment ID (unique) |
| `order_id` | string | Your application's order ID (indexed) |
| `amount` | decimal(10,2) | Payment amount |
| `currency` | string(3) | Currency code |
| `status` | string | Transaction status (indexed, default: `pending`) |
| `payment_method` | string | Payment method (nullable) |
| `idempotency_key` | string | Idempotency key (nullable) |
| `payment_url` | text | Redirect URL (nullable) |
| `raw_request` | json | Request payload (nullable) |
| `raw_response` | json | Response/callback payload (nullable) |
| `paid_at` | timestamp | When payment succeeded (nullable) |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Last update time |

## Testing

```bash
./vendor/bin/phpunit
```

The package includes 82 tests covering:
- Configuration validation
- DTO construction and serialization
- HMAC signature generation and verification (hex + Base64)
- Service layer (create, status, cancel, refund)
- Webhook controller (success, cancel, duplicate, validation)
- Middleware signature verification (raw body)
- OAuth token management (fetch, cache, refresh, errors)

## Postman Collection

A ready-to-use Postman collection is included in `postman/`:

- `Kapital_Bank_API_V1.3.postman_collection.json` — all 9 requests with auto-tests
- `Kapital_Bank_API_V1.3.postman_environment.json` — environment template

Features:
- Auto token refresh on expiry
- Auto-save `payment_id` and `refund_id` between requests
- Auto-generate `X-Idempotency-Key` UUIDs
- Auto-generate `X-Signature` for webhook simulation
- Test assertions on every request

## License

MIT
