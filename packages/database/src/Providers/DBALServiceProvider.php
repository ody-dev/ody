<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Providers;

use Ody\DB\Doctrine\DBAL;
use Ody\Foundation\Providers\ServiceProvider;

class DBALServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Skip initialization during console commands
        if ($this->isRunningInConsole()) {
            return;
        }

        // Get database configuration
        $dbConfig = config('database.environments')[config('app.environment', 'local')];

        // Convert config to Doctrine format if needed
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
    }

    public function register(): void
    {
        // TODO: Implement register() method.
    }
}