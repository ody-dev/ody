<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Facades;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Swoole\Coroutine;

/**
 * Facade for Doctrine DBAL
 *
 * @method static Result executeQuery(string $sql, array $params = [], array $types = [])
 * @method static int executeStatement(string $sql, array $params = [], array $types = [])
 * @method static mixed fetchOne(string $sql, array $params = [], array $types = [])
 * @method static array fetchAllAssociative(string $sql, array $params = [], array $types = [])
 * @method static array fetchAllNumeric(string $sql, array $params = [], array $types = [])
 * @method static array fetchAllKeyValue(string $sql, array $params = [], array $types = [])
 * @method static array fetchFirstColumn(string $sql, array $params = [], array $types = [])
 * @method static mixed transactional(callable $callback)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 */
class DBAL
{
    /**
     * Store connections by coroutine ID
     *
     * @var array<int, Connection>
     */
    protected static array $connections = [];

    /**
     * Pass static method calls to the connection
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return static::connection()->$method(...$args);
    }

    /**
     * Get a DBAL connection
     *
     * @param string|null $name Connection name
     * @return Connection
     */
    public static function connection(?string $name = null): Connection
    {
        $cid = Coroutine::getCid();

        // For non-coroutine context, use the container
        if ($cid < 0) {
            return app('db.dbal');
        }

        $connectionKey = $cid . '-' . ($name ?? 'default');

        if (!isset(self::$connections[$connectionKey])) {
            if ($name !== null && $name !== 'default') {
                $factory = app('db.dbal.factory');
                self::$connections[$connectionKey] = $factory($name);
            } else {
                self::$connections[$connectionKey] = app('db.dbal');
            }

            // Register a defer callback to clean up the connection when the coroutine ends
            Coroutine::defer(function () use ($connectionKey) {
                if (isset(self::$connections[$connectionKey])) {
                    // Ensure any open transaction is rolled back
                    try {
                        if (self::$connections[$connectionKey]->isTransactionActive()) {
                            self::$connections[$connectionKey]->rollBack();
                        }
                    } catch (\Throwable $e) {
                        // Ignore exceptions during cleanup
                        logger()->error("Error during DBAL connection cleanup: " . $e->getMessage());
                    }

                    // Remove the connection reference
                    unset(self::$connections[$connectionKey]);
                }
            });
        }

        return self::$connections[$connectionKey];
    }

    /**
     * Execute a callback in a transaction
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        $connection = static::connection();

        // Start transaction
        $connection->beginTransaction();

        try {
            $result = $callback($connection);
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Create a query builder
     *
     * @return QueryBuilder
     */
    public static function createQueryBuilder(): QueryBuilder
    {
        return static::connection()->createQueryBuilder();
    }

    /**
     * Get the schema manager
     *
     * @return AbstractSchemaManager
     */
    public static function getSchemaManager(): AbstractSchemaManager
    {
        return static::connection()->createSchemaManager();
    }
}