<?php

declare(strict_types=1);

namespace IranMessenger\Laravel;

use Illuminate\Support\ServiceProvider;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Messenger as MessengerContract;
use IranMessenger\MessengerFactory;

/**
 * Optional Laravel glue. This class is only loaded when illuminate/support is
 * installed; the core library has no Laravel dependency.
 */
final class MessengerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/iran-messenger.php', 'iran-messenger');

        $this->app->singleton('iran-messenger', function ($app): MessengerContract {
            $config = (array) $app['config']->get('iran-messenger', []);
            $token = (string) ($config['token'] ?? '');

            if ($token === '') {
                throw new ValidationException(
                    'Set IRAN_MESSENGER_TOKEN or iran-messenger.token before resolving the bot.',
                );
            }

            return MessengerFactory::make(
                (string) ($config['driver'] ?? 'telegram'),
                $token,
                (array) ($config['options'] ?? []),
            );
        });

        $this->app->alias('iran-messenger', MessengerContract::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/iran-messenger.php' => config_path('iran-messenger.php'),
            ], 'iran-messenger-config');
        }
    }
}
