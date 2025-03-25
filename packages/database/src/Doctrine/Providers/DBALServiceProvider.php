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
use Ody\DB\Doctrine\DBALMysQLDriver;
use Ody\Foundation\Providers\ServiceProvider;

class DBALServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // No initialization needed here - will be done on first use
    }

    public function register(): void
    {
        $this->container->singleton('db.dbal', function ($app) {
            $config = config('database.environments')[config('app.environment', 'local')];

            $connectionParams = [
                'driverClass' => DBALMysQLDriver::class,
                'dbname' => $config['database'] ?? $config['db_name'] ?? '',
                'user' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'host' => $config['host'] ?? 'localhost',
                'port' => $config['port'] ?? 3306,
                'charset' => $config['charset'] ?? 'utf8mb4',
                'poolName' => 'dbal-default', // Use a distinct pool name for DBAL
            ];

            $configuration = new Configuration();

            return DriverManager::getConnection($connectionParams, $configuration);
        });

        // Register a reusable factory function for creating DBAL connections
        $this->container->bind('db.dbal.factory', function ($app) {
            return function (string $connectionName = 'default') {
                $config = config('database.environments')[$connectionName] ??
                    config('database.environments')[config('app.environment', 'local')];

                $connectionParams = [
                    'driverClass' => DBALMysQLDriver::class,
                    'dbname' => $config['database'] ?? $config['db_name'] ?? '',
                    'user' => $config['username'] ?? '',
                    'password' => $config['password'] ?? '',
                    'host' => $config['host'] ?? 'localhost',
                    'port' => $config['port'] ?? 3306,
                    'charset' => $config['charset'] ?? 'utf8mb4',
                    'poolName' => "dbal-$connectionName", // Use distinct pool names for different connections
                ];

                $configuration = new Configuration();

                return DriverManager::getConnection($connectionParams, $configuration);
            };
        });
    }
}