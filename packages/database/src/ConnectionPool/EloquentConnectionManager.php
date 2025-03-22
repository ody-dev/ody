<?php

namespace Ody\DB;

use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

class EloquentConnectionManager
{
    private static ?ConnectionPoolAdapter $pool = null;
    private static array $activeConnections = [];

    public static function initialize(array $config, int $poolSize = 64): void
    {
        if (self::$pool === null) {
            self::$pool = new ConnectionPoolAdapter($config, $poolSize);
        }
    }

    public static function getConnection(): Connection
    {
        $cid = Coroutine::getCid();

        // If already have a connection for this coroutine, return it
        if (isset(self::$activeConnections[$cid])) {
            return self::$activeConnections[$cid];
        }

        $pdo = self::$pool->borrow();

        // Create Eloquent connection instance
        $connection = new Connection(
            $pdo,
            $config['db_name'] ?? '',
            $config['prefix'] ?? '',
            $config
        );

        // Store for this coroutine
        self::$activeConnections[$cid] = $connection;

        // Auto-return on coroutine end
        Coroutine::defer(function () use ($cid, $pdo) {
            if (isset(self::$activeConnections[$cid])) {
                unset(self::$activeConnections[$cid]);
                self::$pool->return($pdo);
            }
        });

        return $connection;
    }

    public static function close(): void
    {
        if (self::$pool !== null) {
            self::$pool->close();
            self::$pool = null;
        }
    }
}