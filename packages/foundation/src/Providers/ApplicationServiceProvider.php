<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\Foundation\Providers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Ody\Foundation\Application;
use Ody\Foundation\Http\HandlerPool;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service provider for core application services
 */
class ApplicationServiceProvider extends ServiceProvider
{
    /**
     * Services that should be registered as singletons
     *
     * @var array<string, mixed>
     */
    protected array $singletons = [
        Application::class => null,
        Psr17Factory::class => null,
        ServerRequestFactoryInterface::class => Psr17Factory::class,
        ResponseFactoryInterface::class => Psr17Factory::class,
        StreamFactoryInterface::class => Psr17Factory::class,
        UploadedFileFactoryInterface::class => Psr17Factory::class,
        UriFactoryInterface::class => Psr17Factory::class,
    ];

    /**
     * Register custom services
     *
     * @return void
     */
    public function register(): void
    {
        // Register router with container and middleware


        // Register application
        $this->singleton(Application::class, function ($container) {
            // Get the ServiceProviderManager
            $providerManager = $container->make(\Ody\Foundation\Providers\ServiceProviderManager::class);

            // If ServiceProviderManager isn't registered yet, create it
            if (!$providerManager) {
                $config = $container->has('config') ? $container->make('config') : null;
                $logger = $container->has(LoggerInterface::class) ? $container->make(LoggerInterface::class) : null;
                $providerManager = new \Ody\Foundation\Providers\ServiceProviderManager($container, $config, $logger);
                $container->instance(\Ody\Foundation\Providers\ServiceProviderManager::class, $providerManager);
            }

            // Return the Application with correct constructor parameters
            return new Application($container, $providerManager);
        });

        $this->singleton(HandlerPool::class, function ($container) {
            $config = $container->make(\Ody\Support\Config::class);
            $logger = $container->make(\Psr\Log\LoggerInterface::class);

            $enableCaching = $config->get('app.controller_cache.enabled', true);
            $excludedControllers = $config->get('app.controller_cache.excluded', []);

            return new \Ody\Foundation\Http\HandlerPool(
                $container,
                $logger,
                $enableCaching,
                $excludedControllers
            );
        });

        // Register router with container and middleware manager
        // TODO: gets registered in RouterServiceProvider
//        $this->singleton(Router::class, function ($container) {
//            $middlewareManager = $container->make(MiddlewareManager::class);
//            return new Router(
//                $container,
//                $middlewareManager,
//                $container->make(ControllerPool::class)
//            );
//        });
    }

    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
    }
}