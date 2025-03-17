<?php

namespace Ody\Auth\Providers;

use Ody\Auth\Middleware\AttachUserToRequest;
use Ody\Auth\Middleware\Authenticate;
use Ody\Auth\Middleware\CheckAbilities;
use Ody\Auth\Middleware\CheckForAnyAbility;
use Ody\Foundation\Middleware\MiddlewareRegistry;
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
        // Get the logger for debugging
        $logger = null;
        try {
            $logger = $this->container->has(LoggerInterface::class)
                ? $this->container->make(LoggerInterface::class)
                : null;
        } catch (\Throwable $e) {
            // Fallback if we can't get logger
        }

        // Register a simple version of auth middleware that doesn't depend on AuthManager
        $this->singleton(Authenticate::class, function ($container) use ($logger) {
            // Create authenticate middleware with minimal dependencies
            return new Authenticate(null, $logger, 'sanctum');
        });

        // Register a simple version of the attach user middleware
        $this->singleton(AttachUserToRequest::class, function ($container) use ($logger) {
            // Create middleware with minimal dependencies
            return new AttachUserToRequest(null, $logger);
        });

        // Register ability checking middleware
        $this->singleton(CheckAbilities::class, function ($container) use ($logger) {
            return new CheckAbilities([], $logger);
        });

        $this->singleton(CheckForAnyAbility::class, function ($container) use ($logger) {
            return new CheckForAnyAbility([], $logger);
        });

        // Create debug middleware
        if (class_exists('\Ody\Debug\Middleware\DebugMiddleware')) {
            $this->singleton(\Ody\Debug\Middleware\DebugMiddleware::class, function ($container) use ($logger) {
                return new \Ody\Debug\Middleware\DebugMiddleware('main-debug', $logger);
            });
        }

        // Log successful registration
        if ($logger) {
            $logger->info("Auth service provider registered middleware successfully");
        }
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

        // Ensure middleware is properly registered
        if (!$middlewareRegistry->has('auth')) {
            $logger->info("Registering auth middleware alias");
            $middlewareRegistry->add('auth', Authenticate::class);
        }

        // Register the other auth middleware if needed
        if (!$middlewareRegistry->has('ability')) {
            $middlewareRegistry->add('ability', CheckForAnyAbility::class);
        }

        if (!$middlewareRegistry->has('abilities')) {
            $middlewareRegistry->add('abilities', CheckAbilities::class);
        }

        // Add auth middleware to specific routes via the registry
        try {
            $logger->info("Registering auth middleware with the router");

            // Create an instance of the Authenticate middleware
            $auth = $this->container->make(Authenticate::class);

            // Register it directly with the registry for the 'auth' key
            $middlewareRegistry->add('auth', $auth);

            // Also add it as a pattern for any route with /users path
            $middlewareRegistry->addToPattern('*GET:/users*', $auth);

            $logger->info("Authentication middleware registered successfully");
        } catch (\Throwable $e) {
            $logger->error("Failed to register authentication middleware: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        $logger->info("Auth service provider booted successfully");
    }
}