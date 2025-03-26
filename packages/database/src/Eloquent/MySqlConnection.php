<?php
declare(strict_types=1);

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Eloquent;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Ody\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;
use Ody\DB\ConnectionManager;
use PDOStatement;
use Swoole\Coroutine;
use Swoole\Database\PDOStatementProxy;

class MySqlConnection extends Connection
{
    /**
     * The coroutine ID that owns this connection
     *
     * @var int|null
     */
    protected ?int $ownerCoroutineId = null;

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
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return QueryGrammar
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        ($grammar = new QueryGrammar)->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Set the schema grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultSchemaGrammar(): void
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return SchemaGrammar
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        ($grammar = new SchemaGrammar)->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Configure the PDO prepared statement.
     *
     * @param PDOStatement|PDOStatementProxy $statement
     * @return PDOStatement|PDOStatementProxy
     */
    protected function prepared($statement): PDOStatementProxy|PDOStatement
    {
        $statement->setFetchMode($this->fetchMode);

        $this->event(new StatementPrepared($this, $statement));

        return $statement;
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     * @throws BorrowTimeoutException
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        $pdo = ConnectionManager::getConnection();

        return $this->run($query, $bindings, function ($query, $bindings) use ($pdo) {
            if ($this->pretending()) {
                return [];
            }

            // Use the freshly borrowed connection
            $statement = $this->prepared($pdo->prepare($query));

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }
}