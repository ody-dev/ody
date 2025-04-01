<?php

namespace Ody\CQRS\Providers;

use Ody\AMQP\AMQPClient;
use Ody\CQRS\Listeners\AsyncHandlerReloadListener;
use Ody\CQRS\Messaging\AMQPMessageBroker;
use Ody\CQRS\Messaging\AsyncMessagingBootstrap;
use Ody\CQRS\Messaging\MessageBroker;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Framework\Events\CodeReloaded;

class AsyncMessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Skip registration if components aren't installed
        if (!self::isInstalled()) {
            return;
        }

        // Register the message broker
        $this->app->singleton(MessageBroker::class, function ($app) {
            return new AMQPMessageBroker(
                $app->make(AMQPClient::class)
            );
        });

        // Register the async bootstrap
        $this->app->singleton(AsyncMessagingBootstrap::class, function ($app) {
            return new AsyncMessagingBootstrap(
                $app->make('Ody\CQRS\Interfaces\CommandBusInterface'),
                $app->make(MessageBroker::class),
                $app->make('Psr\Log\LoggerInterface')
            );
        });

        // Register the reload listener
        $this->app->singleton(AsyncHandlerReloadListener::class);
    }

    /**
     * Check if the async messaging components are installed
     */
    public static function isInstalled(): bool
    {
        return class_exists('Ody\AMQP\AMQPClient') &&
            class_exists('Ody\CQRS\Interfaces\CommandBusInterface');
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Skip if components aren't installed
        if (!self::isInstalled()) {
            return;
        }

        // Skip if not enabled in config
        if (!$this->app['config']->get('messaging.async.enabled', false)) {
            return;
        }

        // Register the reload listener
        $this->app['events']->listen(
            CodeReloaded::class,
            AsyncHandlerReloadListener::class
        );

        // Register async handlers on initial boot
        $bootstrap = $this->app->make(AsyncMessagingBootstrap::class);
        $handlerPaths = $this->app['config']->get('cqrs.handler_paths', []);
        $bootstrap->registerAsyncHandlers($handlerPaths);
    }
}