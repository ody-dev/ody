<?php

namespace Ody\CQRS\Bus\Middleware;

abstract class QueryBusMiddleware
{
    /**
     * Handle the query
     *
     * @param object $query
     * @param callable $next
     * @return mixed
     */
    abstract public function handle(object $query, callable $next): mixed;
}