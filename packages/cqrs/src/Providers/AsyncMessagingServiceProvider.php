<?php

namespace Ody\CQRS\Providers;

use Ody\AMQP\AMQPClient;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Listeners\AsyncHandlerReloadListener;
use Ody\CQRS\Messaging\AMQPMessageBroker;
use Ody\CQRS\Messaging\AsyncMessagingBootstrap;
use Ody\CQRS\Messaging\MessageBroker;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Log\LoggerInterface;

class AsyncMessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }
        // Skip registration if components aren't installed
        if (!self::isInstalled()) {
            return;
        }

        // Register the message broker
        $this->container->singleton(MessageBroker::class, function ($app) {
            return new AMQPMessageBroker(
                $app->make(AMQPClient::class)
            );
        });

        // Register the async bootstrap
        $this->container->singleton(AsyncMessagingBootstrap::class, function ($app) {
            return new AsyncMessagingBootstrap(
                $app->make(CommandBusInterface::class),
                $app->make(MessageBroker::class),
                $app->make(LoggerInterface::class)
            );
        });

        // Register the reload listener
        $this->container->singleton(AsyncHandlerReloadListener::class);
    }

    /**
     * Check if the async messaging components are installed
     */
    public static function isInstalled(): bool
    {
        var_dump(class_exists('Ody\AMQP\AMQPClient'));
        var_dump(class_exists('Ody\CQRS\Interfaces\CommandBusInterface'));

        return true;

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
        if ($this->isRunningInConsole()) {
            return;
        }

        // Skip if components aren't installed
        if (!self::isInstalled()) {
            return;
        }

        // Skip if not enabled in config
        if (!$this->container['config']->get('messaging.async.enabled', false)) {
            return;
        }

        // Register the reload listener
//        $this->container['events']->listen(
//            CodeReloaded::class,
//            AsyncHandlerReloadListener::class
//        );

        // Register async handlers on initial boot
        $bootstrap = $this->container->make(AsyncMessagingBootstrap::class);
        $handlerPaths = $this->container['config']->get('cqrs.handler_paths', []);
        $bootstrap->registerAsyncHandlers($handlerPaths);
    }
}