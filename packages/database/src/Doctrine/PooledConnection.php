<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Connection;
use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

/**
 * A Doctrine DBAL Connection that integrates with Swoole connection pooling
 */
class PooledConnection extends Connection
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
     * Set the connection pool adapter.
     *
     * @param ConnectionPoolAdapter $adapter
     * @return $this
     */
    public function setPoolAdapter(ConnectionPoolAdapter $adapter)
    {
        $this->poolAdapter = $adapter;

        // Record the coroutine that created this connection
        if (Coroutine::getCid() !== -1) {
            $this->ownerCoroutineId = Coroutine::getCid();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        // If already connected, nothing to do
        if ($this->_conn !== null) {
            return false;
        }

        // If we have a pool adapter, get connection from pool
        if ($this->poolAdapter !== null) {
            $this->_conn = $this->poolAdapter->borrow();
            return true;
        }

        // Otherwise use normal connection logic
        return parent::connect();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->_conn !== null && $this->poolAdapter !== null) {
            // Return to the pool instead of closing
            $this->poolAdapter->return($this->_conn);
            $this->_conn = null;
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