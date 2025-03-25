<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Eloquent\Facades;

use Illuminate\Database\Connection;
use Ody\DB\Eloquent\ConnectionFactory;

/**
 * @method static int insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static mixed scalar(string $query, array $bindings = [], bool $useReadPdo = true)
 */
class DB
{
    /**
     * Pass static method calls to the default connection
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return static::connection()->$method(...$args);
    }

    /**
     * Get the database connection instance for the current coroutine.
     *
     * @param string|null $name
     * @return Connection
     */
    public static function connection($name = null)
    {
        // This will always return the same connection for this coroutine
        $config = config('database.environments')[config('app.environment', 'local')];
        return ConnectionFactory::make($config, $name ?: 'default');
    }

    /**
     * Begin a fluent query against a database table
     *
     * @param string $table
     * @param string|null $as
     * @return \Illuminate\Database\Query\Builder
     */
    public static function table($table, $as = null)
    {
        return static::connection()->table($table, $as);
    }

    /**
     * Run a select statement against the database
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     */
    public static function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        return static::connection()->select($query, $bindings, $useReadPdo);
    }

    /**
     * Run a transaction in a Swoole-aware way
     *
     * @param \Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(\Closure $callback, $attempts = 1)
    {
        return static::connection()->transaction($callback, $attempts);
    }

    public static function beginTransaction(): void
    {
        static::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        static::connection()->commit();
    }

    public static function rollback(): void
    {
        static::connection()->rollBack();
    }
}