<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Facades;

use Illuminate\Database\Connection;
use Ody\DB\Eloquent;

/**
 * // * @method static \Illuminate\Database\Connection connection(string $name = null)
 * // * @method static \Illuminate\Database\Query\Builder table(string $table, string $as = null)
 * // * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * // * @method static \Illuminate\Database\Query\Builder select(string $query, array $bindings = [], bool $useReadPdo = true)
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
     * Get a database connection instance
     *
     * @param string|null $connection
     * @return Connection
     */
    public static function connection($connection = null)
    {
        return Eloquent::getCapsule()->getConnection($connection);
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
    public static function select($query, $bindings = [], $useReadPdo = true)
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
}