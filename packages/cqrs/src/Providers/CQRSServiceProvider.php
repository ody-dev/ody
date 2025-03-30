<?php

namespace Ody\CQRS\Providers;

use Ody\CQRS\Bus\CommandBus;
use Ody\CQRS\Bus\EventBus;
use Ody\CQRS\Bus\QueryBus;
use Ody\CQRS\Discovery\HandlerScanner;
use Ody\CQRS\Discovery\MiddlewareScanner;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Interfaces\EventBusInterface;
use Ody\CQRS\Interfaces\QueryBusInterface;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use Ody\CQRS\Middleware\MiddlewareRegistry;
use Ody\CQRS\Middleware\PointcutResolver;
use Ody\CQRS\Middleware\SimplePointcutResolver;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Log\LoggerInterface;

class CQRSServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the CQRS services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Register config file
        $this->publishes([
            __DIR__ . '/../../config/cqrs.php' => config_path('cqrs.php'),
        ], 'ody/cqrs');

        // Scan and register handlers
        $this->registerHandlers();

        // Scan and register middleware
        $this->registerMiddleware();
    }

    /**
     * Register the CQRS services.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Register discovery utilities
        $this->registerDiscoveryServices();

        // Register core services
        $this->registerCoreServices();
    }

    /**
     * Register discovery-related services
     *
     * @return void
     */
    protected function registerDiscoveryServices(): void
    {
        $this->container->singleton(HandlerScanner::class);
        $this->container->singleton(MiddlewareScanner::class);
    }

    /**
     * Register the core CQRS services
     *
     * @return void
     */
    protected function registerCoreServices(): void
    {
        // Register middleware components
        $this->container->singleton(PointcutResolver::class, SimplePointcutResolver::class);
        $this->container->singleton(MiddlewareRegistry::class);
        $this->container->singleton(MiddlewareProcessor::class);

        // Register the registries first since they have no dependencies
        $this->container->singleton(CommandHandlerRegistry::class);
        $this->container->singleton(QueryHandlerRegistry::class);
        $this->container->singleton(EventHandlerRegistry::class);

        // Register the query handler resolver
        $this->container->singleton(QueryHandlerResolver::class);

        // First register EventBus
        $this->container->singleton(EventBusInterface::class, function ($app) {
            return new EventBus(
                $app->make(EventHandlerRegistry::class),
                $this->container,
                $app->make(LoggerInterface::class),
                $app->make(MiddlewareProcessor::class)
            );
        });

        // Then register CommandHandlerResolver that might need EventBus
        $this->container->singleton(CommandHandlerResolver::class, function ($app) {
            return new CommandHandlerResolver(
                $app
            );
        });

        // Finally register CommandBus and QueryBus
        $this->container->singleton(CommandBusInterface::class, function ($app) {
            return new CommandBus(
                $app->make(CommandHandlerRegistry::class),
                $app->make(CommandHandlerResolver::class),
                $app->make(MiddlewareProcessor::class)
            );
        });

        $this->container->singleton(QueryBusInterface::class, function ($app) {
            return new QueryBus(
                $app->make(QueryHandlerRegistry::class),
                $app->make(QueryHandlerResolver::class),
                $app->make(MiddlewareProcessor::class)
            );
        });
    }

    /**
     * Register handlers by scanning service classes for attributes
     *
     * @return void
     */
    protected function registerHandlers(): void
    {
        $handlerPaths = config('cqrs.handler_paths', []);

        if (empty($handlerPaths)) {
            return;
        }

        $scanner = $this->container->make(HandlerScanner::class);
        $scanner->scanAndRegister($handlerPaths);
    }

    /**
     * Register middleware by scanning middleware classes for attributes
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        $middlewarePaths = config('cqrs.middleware_paths', []);

        if (empty($middlewarePaths)) {
            return;
        }

        $scanner = $this->container->make(MiddlewareScanner::class);
        $scanner->scanAndRegister($middlewarePaths);
    }
}