<?php

namespace Ody\Auth\Providers;

use Ody\Auth\AuthFactory;
use Ody\Auth\AuthManager;
use Ody\Auth\AuthProviderInterface;
use Ody\Auth\DirectAuthProvider;
use Ody\Auth\Middleware\AuthMiddleware;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Support\Config;

class AuthServiceProvider extends ServiceProvider
{
    protected array $singletons = [
        AuthManager::class => null,
        AuthProviderInterface::class => null,
    ];

    protected array $aliases = [
        'auth' => AuthManager::class,
    ];

    public function register(): void
    {
        // Register AuthFactory
        $this->container->singleton(AuthFactory::class);

        // Register the auth provider (direct or remote based on config)
        $this->container->singleton(AuthProviderInterface::class, function ($container) {
            $config = $container->make(Config::class);
            $authConfig = $config->get('auth.driver', []);

            // Default to direct provider if not specified
            $authType = $authConfig['provider'] ?? 'direct';

            if ($authType === 'direct') {
                // Get user repository (from your Eloquent setup)
                $userRepository = $container->make('user.repository');

                return new DirectAuthProvider(
                    $userRepository,
                    $authConfig['jwt_key'] ?? env('JWT_SECRET_KEY', 'default_secret_key'),
                    $authConfig['token_expiry'] ?? 3600,
                    $authConfig['refresh_token_expiry'] ?? 86400 * 30
                );
            } else {
                // Setup for remote auth provider
                return AuthFactory::createRemoteProvider(
                    $authConfig['service_host'] ?? env('AUTH_SERVICE_HOST', 'localhost'),
                    $authConfig['service_port'] ?? env('AUTH_SERVICE_PORT', 9501),
                    $authConfig['service_id'] ?? env('SERVICE_ID', 'app'),
                    $authConfig['service_secret'] ?? env('SERVICE_SECRET', 'secret')
                );
            }
        });

        // Register the AuthManager
        $this->container->singleton(AuthManager::class, function ($container) {
            return new AuthManager(
                $container->make(AuthProviderInterface::class)
            );
        });

        // Register Auth Middleware
        $this->container->singleton(AuthMiddleware::class, function ($container) {
            return new AuthMiddleware(
                $container->make(AuthManager::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutes(__dir__ . '/../routes.php');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/auth.php' => 'auth.php'
        ], 'ody/auth');
    }
}