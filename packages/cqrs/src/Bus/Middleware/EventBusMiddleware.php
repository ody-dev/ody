<?php

namespace Ody\CQRS\Bus\Middleware;

abstract class EventBusMiddleware
{
    /**
     * Handle the event
     *
     * @param object $event
     * @param callable $next
     * @return void
     */
    abstract public function handle(object $event, callable $next): void;
}