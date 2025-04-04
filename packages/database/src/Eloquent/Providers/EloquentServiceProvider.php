<?php
declare(strict_types=1);
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Eloquent\Providers;

use Ody\Foundation\Providers\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->container);

        $this->container->singleton('db', function ($app) {
            return new \Ody\DB\Eloquent\Facades\DB();
        });
    }

    public function boot(): void
    {
        // Check if already booted FOR THIS CONTAINER INSTANCE if ServiceProviderManager doesn't handle it
        // You might need a non-static property on the provider instance: private bool $hasBooted = false;
        // if ($this->hasBooted) { return; }

        logger()->debug("Booting Eloquent via Service Provider...");

        $capsule = $this->container->make(\Illuminate\Database\Capsule\Manager::class); // Get the singleton
        $config = config('database.environments')[config('app.environment', 'local')]; // Get config

        // Format config (move setConfig logic here or to a helper)
        $eloquentConfig = $this->formatEloquentConfig($config);
        $capsule->addConnection($eloquentConfig, 'default'); // Add connection TO THE SINGLETON

        if ($this->container->make('config')->get('database.enable_connection_pool', false)) {
            // Register resolver ONCE per container boot
            \Illuminate\Database\Connection::resolverFor('mysql', function ($pdo, $database, $prefix, $config) {
                // Ensure ConnectionFactory::make is safe for Swoole/Coroutines
                // It should ideally use the container to resolve dependencies if needed
                // $factory = $this->container->make(\Ody\DB\Eloquent\ConnectionFactory::class);
                // return $factory->make($config);
                return \Ody\DB\Eloquent\ConnectionFactory::make($config); // Assuming make is static and safe
            });
        }

//        $capsule->setAsGlobal();
        $capsule->bootEloquent(); // Still potentially problematic

        // $this->hasBooted = true; // Mark as booted for this provider instance
    }

// Helper function moved from static Eloquent class
    private function formatEloquentConfig(array $config): array
    {
        $config['driver'] = $config['adapter'] ?? 'mysql';
        $config['database'] = $config['db_name'] ?? '';
        if ($this->container->make('config')->get('database.enable_connection_pool', false)) {
            $config['pool_size'] = $this->container->make('config')->get('database.pool_size', 32);
        }
        return $config;
    }
}