<?php

namespace Sarkhanrasimoghlu\KapitalBank;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Sarkhanrasimoghlu\KapitalBank\Auth\OAuthTokenManager;
use Sarkhanrasimoghlu\KapitalBank\Configuration\KapitalBankConfiguration;
use Sarkhanrasimoghlu\KapitalBank\Contracts\KapitalBankServiceInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\ConfigurationInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\HttpClientInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\SignatureGeneratorInterface;
use Sarkhanrasimoghlu\KapitalBank\Contracts\TokenManagerInterface;
use Sarkhanrasimoghlu\KapitalBank\Http\GuzzleHttpClient;
use Sarkhanrasimoghlu\KapitalBank\Security\HmacSignatureGenerator;
use Sarkhanrasimoghlu\KapitalBank\Events\PaymentCreated;
use Sarkhanrasimoghlu\KapitalBank\Listeners\SaveTransactionListener;
use Sarkhanrasimoghlu\KapitalBank\Services\KapitalBankService;

class KapitalBankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kapital-bank.php', 'kapital-bank');

        $this->app->singleton(ConfigurationInterface::class, function ($app) {
            return KapitalBankConfiguration::fromArray($app['config']->get('kapital-bank', []));
        });

        $this->app->singleton(HttpClientInterface::class, function ($app) {
            return new GuzzleHttpClient(
                $app->make(ConfigurationInterface::class),
            );
        });

        $this->app->singleton(SignatureGeneratorInterface::class, function ($app) {
            return new HmacSignatureGenerator(
                $app->make(ConfigurationInterface::class),
            );
        });

        $this->app->singleton(TokenManagerInterface::class, function ($app) {
            return new OAuthTokenManager(
                $app->make(ConfigurationInterface::class),
                $app->make(HttpClientInterface::class),
            );
        });

        $this->app->afterResolving(HttpClientInterface::class, function (HttpClientInterface $client, $app) {
            if ($client instanceof GuzzleHttpClient) {
                $client->setTokenManager($app->make(TokenManagerInterface::class));
            }
        });

        $this->app->singleton(KapitalBankServiceInterface::class, function ($app) {
            return new KapitalBankService(
                httpClient: $app->make(HttpClientInterface::class),
                configuration: $app->make(ConfigurationInterface::class),
                logger: $app->make(LoggerInterface::class),
                events: $app->make(Dispatcher::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/kapital-bank.php' => config_path('kapital-bank.php'),
            ], 'kapital-bank-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'kapital-bank-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/kapital-bank.php');

        $this->app->make(Dispatcher::class)->listen(
            PaymentCreated::class,
            SaveTransactionListener::class,
        );
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ConfigurationInterface::class,
            HttpClientInterface::class,
            SignatureGeneratorInterface::class,
            TokenManagerInterface::class,
            KapitalBankServiceInterface::class,
        ];
    }
}
