<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

class PDOManager
{
    private static ?ConnectionPoolAdapter $pool = null;
    private static array $activeConnections = [];

    public static function initialize(array $config, int $poolSize = 64): void
    {
        if (self::$pool === null) {
            self::$pool = new ConnectionPoolAdapter($config, $poolSize);
        }
    }

    public static function getConnection(): \PDO
    {
        $cid = Coroutine::getCid();

        // If already have a connection for this coroutine, return it
        if (isset(self::$activeConnections[$cid])) {
            return self::$activeConnections[$cid];
        }

        $pdo = self::$pool->borrow();

        // Store for this coroutine
        self::$activeConnections[$cid] = $pdo;

        // Auto-return on coroutine end
        Coroutine::defer(function () use ($cid) {
            if (isset(self::$activeConnections[$cid])) {
                $connection = self::$activeConnections[$cid];
                unset(self::$activeConnections[$cid]);
                self::$pool->return($connection);
            }
        });

        return $pdo;
    }

    public static function close(): void
    {
        if (self::$pool !== null) {
            self::$pool->close();
            self::$pool = null;
        }
    }
}