<?php

namespace Sarkhanrasimoghlu\KapitalBank\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Sarkhanrasimoghlu\KapitalBank\Contracts\KapitalBankServiceInterface;
use Sarkhanrasimoghlu\KapitalBank\DataTransferObjects\PaymentStatus;
use Sarkhanrasimoghlu\KapitalBank\Enums\TransactionStatus;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentFailed;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentSucceeded;
use Sarkhanrasimoghlu\KapitalBank\Exceptions\CallbackException;
use Sarkhanrasimoghlu\KapitalBank\Http\Controllers\CallbackController;
use Sarkhanrasimoghlu\KapitalBank\KapitalBankServiceProvider;

class CallbackControllerTest extends TestCase
{
    private KapitalBankServiceInterface $service;

    protected function getPackageProviders($app): array
    {
        return [KapitalBankServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('kapital-bank.merchant_id', 'test-merchant');
        $app['config']->set('kapital-bank.client_id', 'test-client-id');
        $app['config']->set('kapital-bank.client_secret', 'test-client-secret');
        $app['config']->set('kapital-bank.base_url', 'https://api.kapitalbank.test');
        $app['config']->set('kapital-bank.terminal_id', 'test-terminal');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->service = $this->createMock(KapitalBankServiceInterface::class);
    }

    private function mockServiceForStatus(string $paymentId, TransactionStatus $status): void
    {
        $this->service->method('getPaymentStatus')->willReturn(new PaymentStatus(
            paymentId: $paymentId,
            status: $status,
            amount: 100.00,
            currency: 'AZN',
            rawResponse: [],
        ));
    }

    public function test_it_handles_successful_payment_callback(): void
    {
        Event::fake();

        DB::table('kapital_bank_transactions')->insert([
            'transaction_id' => 'pay_001',
            'order_id' => 'ORDER-001',
            'amount' => 100.00,
            'currency' => 'AZN',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockServiceForStatus('pay_001', TransactionStatus::Succeeded);

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_succeeded',
            'payload' => [
                'id' => 'pay_001',
                'status' => 'succeeded',
                'type' => 'payment',
                'paymentMethod' => 'card',
            ],
        ]);

        $response = $controller->handle($request, $this->service);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getContent());

        $transaction = DB::table('kapital_bank_transactions')
            ->where('transaction_id', 'pay_001')
            ->first();

        $this->assertSame('succeeded', $transaction->status);
        $this->assertNotNull($transaction->paid_at);

        Event::assertDispatched(PaymentSucceeded::class, function ($event) {
            return $event->transactionId === 'pay_001';
        });
    }

    public function test_it_handles_canceled_payment_callback(): void
    {
        Event::fake();

        DB::table('kapital_bank_transactions')->insert([
            'transaction_id' => 'pay_002',
            'order_id' => 'ORDER-002',
            'amount' => 50.00,
            'currency' => 'AZN',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockServiceForStatus('pay_002', TransactionStatus::Canceled);

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_canceled',
            'payload' => [
                'id' => 'pay_002',
                'status' => 'canceled',
                'type' => 'payment',
            ],
        ]);

        $response = $controller->handle($request, $this->service);

        $this->assertSame(200, $response->getStatusCode());

        $transaction = DB::table('kapital_bank_transactions')
            ->where('transaction_id', 'pay_002')
            ->first();

        $this->assertSame('canceled', $transaction->status);
        $this->assertNull($transaction->paid_at);

        Event::assertDispatched(PaymentFailed::class, function ($event) {
            return $event->transactionId === 'pay_002';
        });
    }

    public function test_it_falls_back_to_webhook_payload_when_api_unreachable(): void
    {
        Event::fake();

        DB::table('kapital_bank_transactions')->insert([
            'transaction_id' => 'pay_005',
            'order_id' => 'ORDER-005',
            'amount' => 100.00,
            'currency' => 'AZN',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->method('getPaymentStatus')->willThrowException(new \RuntimeException('API down'));

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_succeeded',
            'payload' => [
                'id' => 'pay_005',
                'status' => 'succeeded',
            ],
        ]);

        $response = $controller->handle($request, $this->service);

        $this->assertSame(200, $response->getStatusCode());

        $transaction = DB::table('kapital_bank_transactions')
            ->where('transaction_id', 'pay_005')
            ->first();

        $this->assertSame('succeeded', $transaction->status);
    }

    public function test_it_throws_exception_for_missing_event(): void
    {
        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Missing event or payload.id');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'payload' => [
                'id' => 'pay_001',
                'status' => 'succeeded',
            ],
        ]);

        $controller->handle($request, $this->service);
    }

    public function test_it_throws_exception_for_missing_payload_id(): void
    {
        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Missing event or payload.id');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_succeeded',
            'payload' => [
                'status' => 'succeeded',
            ],
        ]);

        $controller->handle($request, $this->service);
    }

    public function test_it_throws_exception_for_unknown_event(): void
    {
        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Unknown event');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'unknown_event',
            'payload' => [
                'id' => 'pay_001',
                'status' => 'succeeded',
            ],
        ]);

        $controller->handle($request, $this->service);
    }

    public function test_it_throws_exception_for_non_existent_transaction(): void
    {
        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Transaction not found');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_succeeded',
            'payload' => [
                'id' => 'NON-EXISTENT',
                'status' => 'succeeded',
            ],
        ]);

        $controller->handle($request, $this->service);
    }

    public function test_it_throws_exception_for_duplicate_callback(): void
    {
        DB::table('kapital_bank_transactions')->insert([
            'transaction_id' => 'pay_003',
            'order_id' => 'ORDER-003',
            'amount' => 75.00,
            'currency' => 'AZN',
            'status' => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Duplicate callback received');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_succeeded',
            'payload' => [
                'id' => 'pay_003',
                'status' => 'succeeded',
            ],
        ]);

        $controller->handle($request, $this->service);
    }

    public function test_it_throws_exception_for_duplicate_canceled_callback(): void
    {
        DB::table('kapital_bank_transactions')->insert([
            'transaction_id' => 'pay_004',
            'order_id' => 'ORDER-004',
            'amount' => 75.00,
            'currency' => 'AZN',
            'status' => 'canceled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(CallbackException::class);
        $this->expectExceptionMessage('Duplicate callback received');

        $controller = new CallbackController();
        $request = Request::create('/kapital-bank/callback', 'POST', [
            'event' => 'payment_canceled',
            'payload' => [
                'id' => 'pay_004',
                'status' => 'canceled',
            ],
        ]);

        $controller->handle($request, $this->service);
    }
}
