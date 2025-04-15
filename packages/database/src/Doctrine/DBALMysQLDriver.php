<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection;
use InvalidArgumentException;
use Ody\DB\ConnectionManager;
use PDO;

class DBALMysQLDriver extends AbstractMySQLDriver
{
    /**
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * @var string
     */
    private string $poolName = 'default';

    /**
     * Connect to the database via the connection pool
     *
     * @param array<string, mixed> $params
     * @return Connection
     */
    public function connect(array $params): Connection
    {
        // Retrieve the ConnectionManager instance from the parameters
        if (!isset($params['connectionManager']) || !$params['connectionManager'] instanceof ConnectionManager) {
            throw new InvalidArgumentException(
                'The ConnectionManager instance was not provided in the connection parameters.'
            );
        }

        $connectionManager = $params['connectionManager'];

        // Convert DBAL params to ConnectionManager config format
        $config = [
            'driver' => $params['driver'] ?? 'mysql',
            'host' => $params['host'] ?? 'localhost',
            'port' => $params['port'] ?? 3306,
            'database' => $params['dbname'] ?? '',
            'username' => $params['user'] ?? '',
            'password' => $params['password'] ?? '',
            'charset' => $params['charset'] ?? 'utf8mb4',
            'options' => $params['driverOptions'] ?? [
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            'pool' => $params['pool']
        ];

        // Check if connection pool is enabled
        if ($config['pool']['enabled']) {
            // Use a custom pool name if provided
            if (isset($params['poolName'])) {
                $this->poolName = $params['poolName'];
            }

            // Initialize the pool if it doesn't exist yet
            $pdo = $connectionManager->getConnection($this->poolName, $config);
        } else {
            // Create a direct PDO connection if pool is disabled
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        }

        // Create a connection wrapper that implements Doctrine's Connection interface
        return new PDOConnection($pdo);
    }

    /**
     * When DBAL calls disconnect, we need to implement pool-aware behavior
     */
    public function disconnect(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        // No need to explicitly return the connection since it's auto-returned by ConnectionManager
        // when the coroutine ends, thanks to the autoReturn and bindToCoroutine settings
        $this->connection = null;

        return true;
    }
}