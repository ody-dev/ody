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
use Ody\DB\ConnectionManager;
use Ody\DB\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;
use PDO;

class DBALMysQLDriver extends AbstractMySQLDriver
{
    private ?PDO $connection = null;
    private string $poolName = 'default';
    private array $config = [];

    /**
     * Connect to the database via the connection pool
     *
     * @param array $params
     * @return Connection
     * @throws BorrowTimeoutException
     */
    public function connect(array $params): Connection
    {
        // Convert DBAL params to ConnectionManager config format
        $this->config = [
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
        ];

        // Check if connection pool is enabled
        if (config('database.enable_connection_pool', true)) {
            // Use a custom pool name if provided
            if (isset($params['poolName'])) {
                $this->poolName = $params['poolName'];
            }

            // Initialize the pool if it doesn't exist yet
            ConnectionManager::initPool($this->config, $this->poolName);

            // Borrow a PDO connection from the pool
            $this->connection = ConnectionManager::getConnection($this->poolName);
        } else {
            // Create a direct PDO connection if pool is disabled
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        }

        // Create a connection wrapper that implements Doctrine's Connection interface
        return new PDOConnection($this->connection);
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