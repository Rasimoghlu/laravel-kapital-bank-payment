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
```

| Key | Required | Description |
|-----|----------|-------------|
| `MERCHANT_ID` | Yes | posDetail.merchantId |
| `TERMINAL_ID` | Yes | posDetail.terminalId |
| `CLIENT_ID` | Yes | OAuth2 client ID |
| `CLIENT_SECRET` | Yes | OAuth2 client secret |
| `SECRET_KEY` | No | Webhook HMAC secret (required only if using webhook verification) |
| `BASE_URL` | Yes | API base URL (must be HTTPS in production) |

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
    returnUrl: 'https://yourapp.com/payment/success',
    language: Language::AZ,
);

$response = $service->createPayment($request);

// Redirect customer to payment page
return redirect($response->paymentUrl);

// $response->transactionId    — save this to track the payment
// $response->status           — TransactionStatus::Pending
// $response->confirmationType — "redirect", "qr", "mobile"
// $response->rawResponse      — full API response
```

### Payment Method Types

```php
// Bank card (default)
$request = new PaymentRequest(
    amount: 10.00,
    currency: Currency::AZN,
    orderId: 'ORDER-001',
    paymentMethodType: 'BANK_CARD',
    confirmationType: 'REDIRECT',
    returnUrl: 'https://yourapp.com/success',
);

// BirBank QR payment
$request = new PaymentRequest(
    amount: 10.00,
    currency: Currency::AZN,
    orderId: 'ORDER-002',
    paymentMethodType: 'BIRBANK',
    confirmationType: 'QR',
);

// M10 mobile payment
$request = new PaymentRequest(
    amount: 10.00,
    currency: Currency::AZN,
    orderId: 'ORDER-003',
    paymentMethodType: 'M10',
    confirmationType: 'MOBILE',
);
```

### Payment with Metadata

```php
$request = new PaymentRequest(
    amount: 75.00,
    currency: Currency::AZN,
    orderId: 'ORDER-12346',
    description: 'Online order',
    returnUrl: 'https://yourapp.com/success',
    metadata: [
        'orderNo' => '12346',
        'instalmentTerms' => '3,6,9',
    ],
);

$response = $service->createPayment($request);
```

### Get Payment Status

```php
$status = $service->getPaymentStatus('5b16478f-3d22-46d7-82ed-7182dfd21870');

$status->paymentId;      // "5b16478f-3d22-46d7-82ed-7182dfd21870"
$status->status;          // TransactionStatus::Succeeded
$status->amount;          // 49.99
$status->currency;        // "azn"
$status->paymentMethod;   // PaymentMethod from paymentMethod.type
$status->paidAt;          // DateTimeImmutable or null
$status->rawResponse;     // full API response (includes paid, captured, settled, refunded flags)
```

### Cancel a Payment

Cancel depends on payment status. See the [payment state flow](https://pg.kapitalbank.az/docs) in the official documentation.

```php
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\CancelRequest;

$response = $service->cancelPayment(new CancelRequest(
    paymentId: '5b16478f-3d22-46d7-82ed-7182dfd21870',
));

$response->paymentId;         // "5b16478f-3d22-46d7-82ed-7182dfd21870"
$response->status;             // TransactionStatus::Canceled
$response->cancelationReason;  // "canceled_by_merchant"
$response->cancelationParty;   // "merchant"
```

### Full Refund

```php
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\RefundRequest;

$response = $service->refund(new RefundRequest(
    paymentId: '5b16478f-3d22-46d7-82ed-7182dfd21870',
));

$response->refundId;    // "8ce1a742-428d-4158-93b1-3dfe509f04b5"
$response->status;       // TransactionStatus::Pending
$response->originalId;   // "5b16478f-3d22-46d7-82ed-7182dfd21870"
$response->amount;       // 49.99
$response->currency;     // "azn"
```

### Partial Refund

Payment can be refunded multiple times if refundable amount is not exceeded.

```php
$response = $service->refund(new RefundRequest(
    paymentId: '5b16478f-3d22-46d7-82ed-7182dfd21870',
    amount: 15.00,
    description: 'partial refund',
));
```

### Get Refund Status

```php
$response = $service->getRefundStatus('8ce1a742-428d-4158-93b1-3dfe509f04b5');

$response->refundId;    // "8ce1a742-428d-4158-93b1-3dfe509f04b5"
$response->status;       // TransactionStatus::Succeeded
$response->originalId;   // "5b16478f-..."
$response->amount;       // 15.00
```

## Webhooks

The package automatically registers a `POST /kapital-bank/callback` route protected by signature verification middleware.

### Webhook Payload Format

Kapital Bank sends webhooks in this format:

```json
{
    "event": "payment_succeeded",
    "payload": {
        "id": "e469456c-0a53-4c31-bb43-d77ab197f94a",
        "type": "purchase",
        "paymentMethod": "birbank",
        "status": "succeeded"
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

The package uses **OAuth2 client credentials** flow (`POST /api/oauth2/token`). Tokens are automatically:

- Fetched on first API request with `grant_type=client_credentials` and `scope=email`
- Cached in Laravel's cache store (default TTL: 3500 seconds)
- Refreshed automatically on 401 responses (exactly once per request)
- Protected against thundering herd with cache locks

You never need to manage tokens manually.

## API Endpoints

| Method | Endpoint | Service Method |
|--------|----------|----------------|
| `POST` | `/api/oauth2/token` | Automatic |
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
    // API returned error (400, 500, etc.)
    $statusCode = $e->getCode();
    $context = $e->getContext(); // ['status_code' => 400, 'body' => '{"code":"bad_request",...}']
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
- **HTTPS enforcement** on all configured URLs (HTTP allowed in local/testing)
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

The package includes 81 tests covering:
- Configuration validation
- DTO construction and serialization
- HMAC signature generation and verification (hex + Base64)
- Service layer (create, status, cancel, refund)
- Webhook controller (success, cancel, duplicate, validation)
- Middleware signature verification (raw body)
- OAuth token management (fetch, cache, refresh, errors)

## Postman Collection

A ready-to-use Postman collection is included in `postman/`:

- `Kapital_Bank_API_V1.3.postman_collection.json` — all endpoints with auto-tests
- `Kapital_Bank_API_V1.3.postman_environment.json` — environment template

Features:
- Auto token refresh on expiry
- Auto-save `payment_id` and `refund_id` between requests
- Auto-generate `X-Idempotency-Key` UUIDs
- Auto-generate `X-Signature` for webhook simulation
- Test assertions on every request

## License

MIT
