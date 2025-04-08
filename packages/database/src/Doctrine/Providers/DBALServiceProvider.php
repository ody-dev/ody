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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ody\DB\ConnectionManager;
use Ody\DB\Doctrine\DBALMysQLDriver;
use Ody\Foundation\Providers\ServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DBALServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $config = $this->container->get('config')->get('database.environments', []);
        $config = $config[config('app.environment', 'local')];

        if ($config['pool']['enabled']) {
            /** @var ConnectionManager $connectionManager */
            $pool = $this->container->make(ConnectionManager::class);
            $pool = $pool->getPool($config);
            $pool->warmup();
        }
    }

    public function register(): void
    {
        $this->container->singleton(ConnectionManager::class, function ($app) {
            return new ConnectionManager(
                $app->get('config')->get('database'),
                $app->get(LoggerInterface::class)
            );
        });

        $this->container->bind('dbal.connection.factory', function (ContainerInterface $container) {
            return function (string $name = 'local') use ($container) {
                $config = $this->container->get('config');
                $connectionConfig = $config->get('database.environments', [])[$name] ?? null;

                if (!$connectionConfig) {
                    throw new \InvalidArgumentException("DBAL connection configuration '{$name}' not found.");
                }

                $connectionParams = [
                    'driverClass' => DBALMysQLDriver::class,
                    'dbname' => $connectionConfig['database'] ?? $connectionConfig['db_name'] ?? '',
                    'user' => $connectionConfig['username'] ?? '',
                    'password' => $connectionConfig['password'] ?? '',
                    'host' => $connectionConfig['host'] ?? 'localhost',
                    'port' => $connectionConfig['port'] ?? 3306,
                    'charset' => $connectionConfig['charset'] ?? 'utf8mb4',
                    'poolName' => $connectionConfig['pool']['pool_name'] ?? 'default-' . getmypid(),
                    'connectionManager' => $container->make(ConnectionManager::class),
                    'pool' => $connectionConfig['pool']
                ];

                $configuration = new Configuration();

                return DriverManager::getConnection($connectionParams, $configuration);
            };
        });

        $this->container->bind(Connection::class, function (ContainerInterface $app) {
            // Get the factory we registered above
            $factory = $app->get('dbal.connection.factory');
            // Call the factory to get the default connection instance.
            // Because this binding is scoped, this resolution happens per request/coroutine context.
            return $factory('local'); // Resolve the 'default' connection
        });

        // Optional: Alias for convenience, still resolves the scoped binding above.
        $this->container->alias(Connection::class, 'db.dbal');
    }
}