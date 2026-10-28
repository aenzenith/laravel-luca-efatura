<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use Aenzenith\LucaEFatura\Console\CheckCommand;
use Aenzenith\LucaEFatura\Console\SchemaCommand;
use Aenzenith\LucaEFatura\Console\TaxPayerCommand;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class LucaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/luca.php', 'luca');

        $this->app->singleton(Luca::class, fn (Container $app): Luca => new Luca($app));
        $this->app->alias(Luca::class, 'luca');

        $this->app->bind(Connection::class, fn (Container $app): Connection => $app->make(Luca::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/luca.php' => $this->app->configPath('luca.php'),
            ], ['luca', 'luca-config']);

            $this->commands([
                CheckCommand::class,
                TaxPayerCommand::class,
                SchemaCommand::class,
            ]);
        }

        $this->registerAboutCommand();
    }

    private function registerAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Luca e-Fatura', function (): array {
            /** @var Repository $config */
            $config = $this->app->make('config');
            $default = (string) $config->get('luca.default');
            $schema = $config->get('luca.schema.path');

            return [
                'Connection' => $default,
                'Environment' => (string) $config->get("luca.connections.{$default}.environment"),
                'Company' => (string) ($config->get("luca.connections.{$default}.company.tax_code") ?: '-'),
                'Schema' => is_string($schema) && is_file($schema) ? $schema : 'bundled',
                'Supplier guard' => $config->get('luca.guards.supplier_matches_company') ? 'on' : 'off',
            ];
        });
    }
}
