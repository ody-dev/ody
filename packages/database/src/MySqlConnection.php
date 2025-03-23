<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Illuminate\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

class MySqlConnection extends Connection
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
     * {@inheritdoc}
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        // Ensure we're using MySQL grammar
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultPostProcessor();

        if (Coroutine::getCid() !== -1) {
            $this->ownerCoroutineId = Coroutine::getCid();
        }
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\MySqlGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        ($grammar = new QueryGrammar)->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Set the schema grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultSchemaGrammar()
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\MySqlGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        ($grammar = new SchemaGrammar)->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Set the connection pool adapter.
     *
     * @param ConnectionPoolAdapter $adapter
     * @return $this
     */
    public function setPoolAdapter(ConnectionPoolAdapter $adapter)
    {
        $this->poolAdapter = $adapter;
        return $this;
    }

    /**
     * Configure the PDO prepared statement.
     *
     * @param \PDOStatement|\Swoole\Database\PDOStatementProxy $statement
     * @return \PDOStatement|\Swoole\Database\PDOStatementProxy
     */
    protected function prepared($statement)
    {
        $statement->setFetchMode($this->fetchMode);

        $this->event(new \Illuminate\Database\Events\StatementPrepared($this, $statement));

        return $statement;
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared(
                $this->getPdoForSelect($useReadPdo)->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        // If we have a pool adapter, return the PDO to the pool
        if ($this->poolAdapter && $this->pdo) {
            // Don't directly return the PDO, let the PDO be handled by the garbage collector
            // This avoids the type error with PDOProxy
            $this->pdo = null;
            $this->readPdo = null;
        } else {
            // Default disconnect behavior
            $this->setPdo(null)->setReadPdo(null);
        }
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    public function reconnectIfMissingConnection()
    {
        if (is_null($this->pdo)) {
            // If using pool, get a connection from the pool
            if ($this->poolAdapter) {
                $this->pdo = $this->poolAdapter->borrow();
            } else {
                $this->reconnect();
            }
        }
    }

    /**
     * Return the connection to the pool when the coroutine ends.
     */
    public function __destruct()
    {
        // Ensure connection is returned to pool
        if ($this->pdo !== null) {
            $this->disconnect();
        }
    }
}