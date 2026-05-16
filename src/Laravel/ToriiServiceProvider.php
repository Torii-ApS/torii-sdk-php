<?php

declare(strict_types=1);

namespace Torii\Backend\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Torii\Backend\Torii;

/**
 * Laravel service provider. Wires `Torii\Backend\Torii` into the container as
 * a singleton driven by `config/torii.php`.
 *
 * Register in `bootstrap/providers.php` (Laravel 11+) or `config/app.php`
 * (Laravel 10):
 *
 *     return [
 *         // ...
 *         \Torii\Backend\Laravel\ToriiServiceProvider::class,
 *     ];
 *
 * Then publish the config:
 *
 *     php artisan vendor:publish --tag=torii-config
 */
class ToriiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/torii.php', 'torii');

        $this->app->singleton(Torii::class, function (Application $app): Torii {
            $config = $app['config']->get('torii');
            $secretKey = $config['secret_key'] ?? null;
            if (!is_string($secretKey) || $secretKey === '') {
                throw new \RuntimeException(
                    'torii.secret_key is not configured. Set TORII_SECRET_KEY in your .env.'
                );
            }
            return Torii::create(
                secretKey: $secretKey,
                apiUrl: $config['api_url'] ?? null,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../../config/torii.php' => $this->configPath('torii.php')],
                'torii-config',
            );
        }
    }

    private function configPath(string $name): string
    {
        // Compatibility shim — Laravel exposes config_path() globally but
        // referencing it directly couples this code to the global helper
        // file being loaded. Hit the container instead.
        return $this->app->basePath('config/' . $name);
    }
}
