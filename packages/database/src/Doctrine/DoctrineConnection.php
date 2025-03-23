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

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\Driver\PDO\Statement;
use Doctrine\DBAL\ParameterType;
use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

class DoctrineConnection extends BaseConnection
{
    /**
     * The connection pool adapter
     *
     * @var ConnectionPoolAdapter|null
     */
    protected $poolAdapter = null;

    /**
     * The coroutine ID that owns this connection
     *
     * @var int|null
     */
    protected $ownerCoroutineId = null;

    /**
     * Flag to track if connection is borrowed from pool
     *
     * @var bool
     */
    protected $isPooledConnection = false;

    /**
     * Set the connection pool adapter.
     *
     * @param ConnectionPoolAdapter $adapter
     * @return $this
     */
    public function setPoolAdapter(ConnectionPoolAdapter $adapter)
    {
        $this->poolAdapter = $adapter;
        $this->isPooledConnection = true;

        // Record the coroutine that created this connection
        if (Coroutine::getCid() !== -1) {
            $this->ownerCoroutineId = Coroutine::getCid();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null)
    {
        // Ensure we have a valid connection
        $this->ensureConnection();

        // Call parent method with the restored connection
        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    /**
     * {@inheritdoc}
     */
    public function executeStatement(string $sql, array $params = [], array $types = [])
    {
        // Ensure we have a valid connection
        $this->ensureConnection();

        // Call parent method with the restored connection
        return parent::executeStatement($sql, $params, $types);
    }

    /**
     * Ensure we have a valid connection from the pool
     *
     * @return void
     */
    protected function ensureConnection()
    {
        // If we don't have an active connection but we have a pool adapter, get one from the pool
        if (!$this->isConnected() && $this->poolAdapter) {
            $pdo = $this->poolAdapter->borrow();
            $this->_conn = $pdo;
            $this->isPooledConnection = true;
        } elseif (!$this->isConnected()) {
            // Standard connection establishment
            $this->connect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->isPooledConnection && $this->_conn !== null) {
            // Return to the pool instead of closing
            $this->poolAdapter->return($this->_conn);
            $this->_conn = null;
            $this->isPooledConnection = false;
            return;
        }

        // Default behavior
        parent::close();
    }

    /**
     * Return the connection to the pool when the object is destroyed
     */
    public function __destruct()
    {
        $this->close();
    }
}