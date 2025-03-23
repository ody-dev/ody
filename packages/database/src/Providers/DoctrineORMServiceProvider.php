<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Providers;

use Ody\DB\Doctrine\DBAL;
use Ody\DB\Doctrine\ORM\EntityManagerFactory;
use Ody\Foundation\Providers\ServiceProvider;

class DoctrineORMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the EntityManager factory
        $this->container->singleton('doctrine.orm.factory', function() {
            return new EntityManagerFactory();
        });

        // Register a default EntityManager
        $this->container->singleton('doctrine.orm.em', function($app) {
            $dbConfig = config('database.environments')[config('app.environment', 'local')];

            // Convert DB config to Doctrine format
            $connectionParams = [
                'driver' => 'pdo_mysql',
                'host' => $dbConfig['host'] ?? 'localhost',
                'port' => $dbConfig['port'] ?? 3306,
                'dbname' => $dbConfig['database'] ?? $dbConfig['db_name'] ?? '',
                'user' => $dbConfig['username'] ?? '',
                'password' => $dbConfig['password'] ?? '',
                'charset' => $dbConfig['charset'] ?? 'utf8mb4',
            ];

            // Add pooling configuration if enabled
            if (config('database.enable_connection_pool', false)) {
                $connectionParams['use_pooling'] = true;
                $connectionParams['pool_size'] = config('database.pool_size', 32);
            }

            // Get ORM config
            $ormConfig = config('doctrine.orm', [
                'dev_mode' => config('app.debug', false),
                'entity_paths' => [
                    base_path('app/Entities')
                ],
                'proxy_dir' => storage_path('proxies'),
                'attribute_driver' => true
            ]);

            // Create and return an EntityManager
            return EntityManagerFactory::create($connectionParams, $ormConfig);
        });
    }

    public function boot(): void
    {
        // Skip initialization during console commands
        if ($this->isRunningInConsole()) {
            return;
        }

        // Get database configuration
        $dbConfig = config('database.environments')[config('app.environment', 'local')];

        // Convert config to Doctrine format
        $doctrineConfig = [
            'driver' => 'pdo_mysql',
            'host' => $dbConfig['host'] ?? 'localhost',
            'port' => $dbConfig['port'] ?? 3306,
            'dbname' => $dbConfig['database'] ?? $dbConfig['db_name'] ?? '',
            'user' => $dbConfig['username'] ?? '',
            'password' => $dbConfig['password'] ?? '',
            'charset' => $dbConfig['charset'] ?? 'utf8mb4',
            'wrapperClass' => \Ody\DB\Doctrine\PooledConnection::class,
        ];

        // Add pooling configuration if enabled
        if (config('database.enable_connection_pool', false)) {
            $doctrineConfig['use_pooling'] = true;
            $doctrineConfig['pool_size'] = config('database.pool_size', 32);
        }

        // Boot DBAL with configuration
        DBAL::boot($doctrineConfig);

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/doctrine.php' => 'doctrine.php'
        ], 'ody/doctrine');
    }
}