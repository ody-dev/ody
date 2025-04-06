<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Providers;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Ody\DB\ConnectionManager;
use Ody\DB\Doctrine\DBALMysQLDriver;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Log\LoggerInterface;

class DBALServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $config = config('database.environments')[config('app.environment', 'local')];
        if ($config['pool']['enabled']) {
            $pool = $this->container->make(ConnectionManager::class);
            $pool = $pool->getPool($config);
            $pool->warmup();
        }
    }

    public function register(): void
    {
        $this->container->singleton(ConnectionManager::class, function ($app) {
            // Inject necessary dependencies from the container
            $config = $app->make('config')->get('database'); // Get database config
            $logger = $app->make(LoggerInterface::class); // Get logger
            return new ConnectionManager($config, $logger);
        });

//        $this->container->bind(DBALMysQLDriver::class, function($app) {
//            return new DBALMysQLDriver($app->make(ConnectionManager::class));
//        });

        $this->container->bind('db.dbal', function ($app) {
            $config = config('database.environments')[config('app.environment', 'local')];

            $connectionParams = [
                'driverClass' => DBALMysQLDriver::class,
                'dbname' => $config['database'] ?? $config['db_name'] ?? '',
                'user' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'host' => $config['host'] ?? 'localhost',
                'port' => $config['port'] ?? 3306,
                'charset' => $config['charset'] ?? 'utf8mb4',
                'poolName' => $config['pool_name'] ?? 'default-' . getmypid(),
                'connectionManager' => $app->make(ConnectionManager::class),
                'pool' => $config['pool']
            ];

            $configuration = new Configuration();

            return DriverManager::getConnection($connectionParams, $configuration);
        });

        // Register a reusable factory function for creating DBAL connections
//        $this->container->bind('db.dbal.factory', function ($app) {
//            return function (string $connectionName = 'default') use ($app) {
//                $config = config('database.environments')[$connectionName] ??
//                    config('database.environments')[config('app.environment', 'local')];
//
//                $connectionParams = [
//                    'driverClass' => DBALMysQLDriver::class,
//                    'dbname' => $config['database'] ?? $config['db_name'] ?? '',
//                    'user' => $config['username'] ?? '',
//                    'password' => $config['password'] ?? '',
//                    'host' => $config['host'] ?? 'localhost',
//                    'port' => $config['port'] ?? 3306,
//                    'charset' => $config['charset'] ?? 'utf8mb4',
//                    'poolName' => $config['pool_name'] ?? 'default-' . getmypid(),
//                ];
//
//                $configuration = new Configuration();
//
//                return DriverManager::getConnection($connectionParams, $configuration);
//            };
//        });
    }
}