<?php

namespace Ody\AMQP;

class ServerEvents
{
    private static array $serverStartCallbacks = [];
    private static array $workerStartCallbacks = [];

    public static function onServerStart(callable $callback): void
    {
        self::$serverStartCallbacks[] = $callback;
    }

    public static function onWorkerStart(callable $callback): void
    {
        self::$workerStartCallbacks[] = $callback;
    }

    public static function triggerServerStart(): void
    {
        foreach (self::$serverStartCallbacks as $callback) {
            call_user_func($callback);
        }
    }

    public static function triggerWorkerStart(int $workerId): void
    {
        foreach (self::$workerStartCallbacks as $callback) {
            \Swoole\Coroutine::create(function () use ($callback, $workerId) {
                call_user_func($callback, $workerId);
            });
        }
    }
}