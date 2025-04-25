<?php

namespace Ody\Auth\Providers;

use Ody\Auth\AdapterInterface;
use Ody\Auth\Authentication;
use Ody\Auth\AuthenticationInterface;
use Ody\Auth\JwtAdapter;
use Ody\Auth\Middleware\AuthenticationMiddleware;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Support\Config;
use Psr\Http\Message\ResponseFactoryInterface;

class AuthServiceProvider extends ServiceProvider
{
    protected array $singletons = [
        AuthenticationInterface::class => null,
        AdapterInterface::class => null,
    ];

    protected array $aliases = [
        'auth' => AuthenticationInterface::class,
    ];

    public function register(): void
    {
        // Register the authentication adapter (JWT implementation)
        $this->container->singleton(AdapterInterface::class, function ($container) {
            $config = $container->make(Config::class);
            $authConfig = $config->get('auth.driver', []);

            // Default to JWT adapter
            $jwtKey = $authConfig['jwt_key'] ?? env('JWT_SECRET_KEY', 'default_secret_key');

            // Revoked token callback - can use your existing token revocation logic
            $tokenRevokedCallback = function ($token) use ($container) {
                // You could implement this based on your existing token revocation mechanism
                // For example, using a TokenRepository to check if a token is revoked
                if ($container->has('token.repository')) {
                    return $container->make('token.repository')->isRevoked($token);
                }
                return false;
            };

            return new JwtAdapter(
                $jwtKey,
                'Bearer',
                'Authorization',
                'HS256',
                $tokenRevokedCallback
            );
        });

        // Register the Authentication service
        $this->container->singleton(AuthenticationInterface::class, function ($container) {
            return new Authentication(
                $container->make(AdapterInterface::class),
                $container->make(ResponseFactoryInterface::class)
            );
        });

        // Register Auth Middleware
        $this->container->singleton(AuthenticationMiddleware::class, function ($container) {
            return new AuthenticationMiddleware(
                $container->make(AuthenticationInterface::class)
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