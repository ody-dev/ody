<?php

namespace Ody\Auth\Providers;

use Ody\Auth\Middleware\Authenticate;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Log\LoggerInterface;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Get the middleware registry
        $middlewareRegistry = $this->container->make(MiddlewareRegistry::class);
        $logger = $this->container->make(LoggerInterface::class);

        try {
            // Create an instance of the Authenticate middleware
            $auth = new Authenticate(null, $logger, 'api');

            // Register it directly with the registry
            $middlewareRegistry->add('auth', $auth);
            $middlewareRegistry->add('auth.api', $auth);

            logger()->info("Authentication middleware registered successfully");
        } catch (\Throwable $e) {
            logger()->error("Failed to register authentication middleware: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}