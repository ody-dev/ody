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

use Ody\DB\ConnectionManager;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Log\LoggerInterface;

class EloquentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->container);

        $this->container->singleton('db', function ($app) {
            return new \Ody\DB\Eloquent\Facades\DB();
        });

        $this->container->singleton(ConnectionManager::class, function ($app) {
            // Inject necessary dependencies from the container
            $config = $app->make('config')->get('database'); // Get database config
            $logger = $app->make(LoggerInterface::class); // Get logger
            return new ConnectionManager($config, $logger);
        });
    }

    public function boot(): void
    {
        $capsule = $this->container->make(\Illuminate\Database\Capsule\Manager::class); // Get the singleton
        $config = config('database.environments')[config('app.environment', 'local')]; // Get config

        // Format config (move setConfig logic here or to a helper)
        $eloquentConfig = $this->formatEloquentConfig($config);
        $capsule->addConnection($eloquentConfig, 'default');

        if ($config['pool']['enabled']) {
            /** @var ConnectionManager $pool */
            $pool = $this->container->make(ConnectionManager::class);
            $pool = $pool->getPool($config);
            $pool->warmup();

            // Register resolver ONCE per container boot
            \Illuminate\Database\Connection::resolverFor('mysql', function ($pdo, $database, $prefix, $config) {
                // Ensure ConnectionFactory::make is safe for Swoole/Coroutines
                // It should ideally use the container to resolve dependencies if needed
                // $factory = $this->container->make(\Ody\DB\Eloquent\ConnectionFactory::class);
                // return $factory->make($config);
                return (new \Ody\DB\Eloquent\ConnectionFactory(
                    $this->container->make(ConnectionManager::class)
                ))->make($config); // Assuming make is static and safe
            });
        }

//        $capsule->setAsGlobal();
        $capsule->bootEloquent();
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