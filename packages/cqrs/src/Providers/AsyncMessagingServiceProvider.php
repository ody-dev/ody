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
        $this->hasDependenciesInstalled();

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
    public function hasDependenciesInstalled(): bool
    {
        if (!class_exists(\Ody\AMQP\AMQPClient::class)) {
            throw new \Exception('AMPQ module is not installed, run composer require ody/amqp');
        }

        if (!class_exists(\Ody\CQRS\Bus\CommandBus::class)) {
            throw new \Exception('CQRS module is not installed, run composer require ody/cqrs');
        }

        return true;
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        $this->hasDependenciesInstalled();

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