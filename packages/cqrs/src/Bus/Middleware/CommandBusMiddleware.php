<?php

namespace Ody\CQRS\Bus\Middleware;

abstract class CommandBusMiddleware
{
    /**
     * Handle the command
     *
     * @param object $command
     * @param callable $next
     * @return void
     */
    abstract public function handle(object $command, callable $next): void;
}