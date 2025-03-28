<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Bus\Middleware\QueryBusMiddleware;
use Ody\CQRS\Interfaces\QueryBus as QueryBusInterface;

class QueryBus implements QueryBusInterface
{
    /**
     * @var QueryBusInterface
     */
    protected QueryBusInterface $bus;

    /**
     * @var array
     */
    protected array $middleware = [];

    /**
     * @param QueryBusInterface $bus
     */
    public function __construct(QueryBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * Add middleware to the query bus
     *
     * @param QueryBusMiddleware $middleware
     * @return self
     */
    public function addMiddleware(QueryBusMiddleware $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch a query through the middleware stack
     *
     * @param object $query
     * @return mixed
     */
    public function dispatch(object $query): mixed
    {
        return $this->executeMiddlewareStack($query, 0);
    }

    /**
     * Execute middleware stack recursively
     *
     * @param object $query
     * @param int $index
     * @return mixed
     */
    protected function executeMiddlewareStack(object $query, int $index): mixed
    {
        if ($index >= count($this->middleware)) {
            // When we've gone through all middleware, execute the actual query
            return $this->bus->dispatch($query);
        }

        // Execute the current middleware
        $middleware = $this->middleware[$index];

        return $middleware->handle(
            $query,
            function ($query) use ($index) {
                // Move to the next middleware
                return $this->executeMiddlewareStack($query, $index + 1);
            }
        );
    }
}