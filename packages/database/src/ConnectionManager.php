<?php
declare(strict_types=1);

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Ody\ConnectionPool\ConnectionPoolFactory;
use Ody\ConnectionPool\KeepaliveCheckerInterface;
use Ody\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;
use Ody\ConnectionPool\Pool\PoolInterface;
use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class ConnectionManager
{
    /**
     * @var array<string, PoolInterface<PDO>>
     */
    protected array $pools = [];

    /**
     * @var string
     */
    protected string $workerId;

    protected LoggerInterface $logger;

    protected array $globalDbConfig;

    public function __construct(array $globalDbConfig, LoggerInterface $logger)
    {
        $this->globalDbConfig = $globalDbConfig; // Store relevant global config if needed
        $this->logger = $logger;

        register_shutdown_function(function () {
            self::closeAll();
        });
    }

    /**
     * Initialize a connection pool for the given configuration
     *
     * @param array $config
     * @param string $name
     * @return PoolInterface
     */
    public function getPool(array $config): PoolInterface
    {
        $workerId = getmypid();
        if (isset($this->pools[$workerId])) {
            return $this->pools[$workerId];
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? $config['db_name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        $connectionsPerWorker = $config['pool']['connections_per_worker'] ?? 64;

        // Create a pool with multiple connections per worker
        $poolFactory = ConnectionPoolFactory::create(
            size: (int)$connectionsPerWorker,
            factory: new PDOConnectionFactory(
                dsn: $dsn,
                username: $config['username'] ?? '',
                password: $config['password'] ?? '',
                options: $config['options'] ?? [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
            )
        );

        $poolFactory->setMinimumIdle($config['pool']['minimum_idle'] ?? max(2, $connectionsPerWorker / 2));
        $poolFactory->setIdleTimeoutSec($config['pool']['idle_timeout'] ?? 60.0);
        $poolFactory->setMaxLifetimeSec($config['max_lifetime'] ?? 3600.0);
        $poolFactory->setBorrowingTimeoutSec($config['borrowing_timeout'] ?? 0.5);
        $poolFactory->setReturningTimeoutSec($config['returning_timeout'] ?? 0.1);
        $poolFactory->setLeakDetectionThresholdSec($config['leak_detection_threshold'] ?? 10.0);
        $poolFactory->setAutoReturn(true);
        $poolFactory->setBindToCoroutine(true);

        $poolFactory->addKeepaliveChecker(
            new class ($config) implements KeepaliveCheckerInterface {

                private array $config;

                public function __construct($config)
                {
                    $this->config = $config;
                }

                public function check(mixed $connection): bool
                {
                    if (!$connection instanceof \PDO) return false;
                    try {
                        $connection->getAttribute(\PDO::ATTR_SERVER_INFO);
                        return true;
                    } catch (\Throwable) {
                        return false; // Connection is likely dead
                    }
                }

                public function getIntervalSec(): float
                {
                    if (isset($this->config['pool']['keep_alive_check_interval'])) {
                        return (float)$this->config['pool']['keep_alive_check_interval'];
                    }
                    // Has to be lower than MySQL wait_timeout
                    return 60;
                }
            },
        );

        // Add a connection checker to verify connections aren't in a transaction
        $poolFactory->addConnectionChecker(function (PDO $connection): bool {
            try {
                return !$connection->inTransaction();
            } catch (Throwable) {
                return false;
            }
        });


        $poolInstanceName = $config['pool']['pool_name'] . '-' . $workerId ?? 'pool-' . $workerId;
        $pool = $poolFactory->instantiate($poolInstanceName);

        $this->pools[$workerId] = $pool;
        $this->logger->debug("Initialized pool: {$poolInstanceName}");

        $pool->warmup();

        return $pool;
    }

    /**
     * Close all connection pools
     */
    public function closeAll(): void
    {
        foreach ($this->pools as $name => $pool) {
            $this->logger->debug("Closing connection pool: $name");
            // Pool closing logic might be needed depending on the pool library
        }
        $this->pools = [];
    }

    /**
     * Get a PDO connection from the pool
     *
     * @param string $name
     * @param array $config
     * @return PDO
     */
    public function getConnection(string $name = 'default', array $config = []): PDO
    {
        $pool = $this->getPool($config);


        try {
            $conn = $pool->borrow();
            $this->logger->debug("Borrowed connection from pool: {$name}, stats: " . json_encode($pool->stats()));
            return $conn;
        } catch (BorrowTimeoutException $e) {
            $this->logger->error("Timeout borrowing connection from pool: {$name}", ['exception' => $e]);
            throw new RuntimeException("Timeout borrowing connection from pool '$name'", 0, $e);
        }
    }
}