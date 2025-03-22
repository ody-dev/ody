<?php

namespace Ody\DB;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

class DoctrineConnectionManager
{
    private static ?ConnectionPoolAdapter $pool = null;
    private static array $activeConnections = [];

    public static function initialize(array $config, int $poolSize = 64): void
    {
        if (self::$pool === null) {
            self::$pool = new ConnectionPoolAdapter($config, $poolSize);
        }
    }

    public static function getConnection(): DoctrineConnection
    {
        $cid = Coroutine::getCid();

        // If already have a connection for this coroutine, return it
        if (isset(self::$activeConnections[$cid])) {
            return self::$activeConnections[$cid];
        }

        $pdo = self::$pool->borrow();

        // Create a Doctrine connection that wraps our pooled PDO
        $connection = DriverManager::getConnection([
            'pdo' => $pdo,
            'dbname' => $config['db_name'] ?? '',
            // other Doctrine config
        ]);

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