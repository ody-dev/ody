<?php

namespace Ody\Middleware\Providers;

use Ody\Container\Container;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Middleware\MiddlewareManager;
use Ody\Middleware\MiddlewareRegistry;
use Ody\Support\Config;
use Psr\Log\LoggerInterface;

class MiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register MiddlewareRegistry as a singleton
        $this->singleton(MiddlewareRegistry::class, function (Container $container) {
            $logger = $container->make(LoggerInterface::class);

            // Create registry (without loading config yet)
            return new MiddlewareRegistry($container, $logger);
        });

        // Register MiddlewareManager as a singleton
        $this->singleton(MiddlewareManager::class, function (Container $container) {
            $logger = $container->make(LoggerInterface::class);
            $config = $container->make(Config::class);

            // Create manager
            $manager = new MiddlewareManager($container, $logger);

            // Load configuration here - ONLY ONCE
            $middlewareConfig = $config->get('middleware', []);
            if (is_array($middlewareConfig)) {
                $manager->registerFromConfig($middlewareConfig);
            }

            return $manager;
        });

        // Add middleware manager alias for easier access
        $this->alias(MiddlewareManager::class, 'middleware');
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // No bootstrapping needed as the middleware configuration
        // is loaded during registration
    }
}