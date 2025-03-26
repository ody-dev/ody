<?php

namespace Ody\AMQP;

class ServerEventManager
{
    private static array $startCallbacks = [];

    public static function onStart(callable $callback): void
    {
        self::$startCallbacks[] = $callback;
    }

    public static function triggerStart(): void
    {
        foreach (self::$startCallbacks as $callback) {
            // Execute each callback in its own coroutine
//            call_user_func($callback);
            \Swoole\Coroutine\run(function () use ($callback) {
                call_user_func($callback);
            });
        }
    }
}