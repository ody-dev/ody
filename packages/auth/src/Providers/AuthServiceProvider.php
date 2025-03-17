<?php

namespace Ody\Auth\Providers;

use Ody\Auth\AuthManager;
use Ody\Auth\Guard;
use Ody\Auth\TokenGuard;
use Ody\Auth\UserProvider;
use Ody\Foundation\Providers\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->container->singleton('auth', function ($container) {
            return new AuthManager($container);
        });

        $this->container->singleton('auth.driver', function ($container) {
            return $container->make('auth')->guard();
        });

        $this->container->singleton('auth.user.provider', function ($container) {
            $config = $container->make('config');
            $provider = $config->get('auth.providers.users', [
                'driver' => 'database',
                'model' => '\\App\\Models\\User',
            ]);

            return new UserProvider($provider['model']);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register token guard
        $this->container->make('auth')->extend('token', function ($app, $name, array $config) {
            $guard = new TokenGuard(
                $this->container->make('auth.user.provider'),
                $this->container->make('request'),
                $config['input_key'] ?? 'api_token',
                $config['storage_key'] ?? 'api_token',
                $config['hash'] ?? false
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        // Register sanctum-like API guard
        $this->container->make('auth')->extend('sanctum', function ($app, $name, array $config) {
            $guard = new Guard(
                $this->container->make('auth'),
                $config['expiration'] ?? null,
                $config['provider'] ?? null
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}