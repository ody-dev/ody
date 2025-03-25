<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Ody\DB\ConnectionPool\ConnectionPoolFactory;
use Ody\DB\ConnectionPool\PDOConnectionFactory;
use Ody\DB\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;
use Ody\DB\ConnectionPool\Pool\PoolInterface;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as MySQLDriver;
use PDO;

class DBALMysQLDriver extends AbstractMySQLDriver
{
    private static ?PoolInterface $connectionPool = null;
    private ?PDO $connection = null;

    /**
     * Initialize the connection pool
     *
     * @param int $poolSize
     * @param float $idleTimeoutSec
     * @param float $maxLifetimeSec
     * @return void
     */
    public static function initializePool(
        int $poolSize = 10,
        float $idleTimeoutSec = 30.0,
        float $maxLifetimeSec = 300.0
    ): void {
        if (self::$connectionPool !== null) {
            return;
        }

        // This would need to be configured based on your database settings
        $factory = new PDOConnectionFactory(
            dsn: 'mysql:host=localhost;dbname=your_database',
            username: 'username',
            password: 'password',
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $poolFactory = ConnectionPoolFactory::create($poolSize, $factory);
        $poolFactory->setIdleTimeoutSec($idleTimeoutSec);
        $poolFactory->setMaxLifetimeSec($maxLifetimeSec);
        $poolFactory->setBindToCoroutine(true);
        $poolFactory->setAutoReturn(true);

        self::$connectionPool = $poolFactory->instantiate('doctrine-dbal-pool');
    }

    /**
     * Connect to the database via the connection pool
     *
     * @param array $params
     * @return Connection
     * @throws BorrowTimeoutException
     */
    public function connect(array $params): Connection
    {
        if (self::$connectionPool === null) {
            throw new \RuntimeException('Connection pool has not been initialized. Call DBALMySQLDriver::initializePool() first.');
        }

        // Borrow a PDO connection from the pool
        $this->connection = self::$connectionPool->borrow();

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

        // Return the connection to the pool
        self::$connectionPool->return($this->connection);
        $this->connection = null;

        return true;
    }
}