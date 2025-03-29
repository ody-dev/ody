<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Exception\QueryHandlerException;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\QueryBus as QueryBusInterface;
use Ody\CQRS\Middleware\MiddlewareProcessor;

class QueryBus implements QueryBusInterface
{
    /**
     * @var array List of middleware to apply
     */
    private array $middleware = [];

    /**
     * @param QueryHandlerRegistry $handlerRegistry
     * @param QueryHandlerResolver $handlerResolver
     * @param MiddlewareProcessor|null $middlewareProcessor
     */
    public function __construct(
        private QueryHandlerRegistry $handlerRegistry,
        private QueryHandlerResolver $handlerResolver,
        private ?MiddlewareProcessor $middlewareProcessor = null
    )
    {
    }

    /**
     * Add middleware to the query bus
     *
     * @param object $middleware The middleware instance
     * @return self
     */
    public function addMiddleware(object $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatches a query to its handler and returns the result
     *
     * @param object $query
     * @return mixed
     * @throws HandlerNotFoundException
     * @throws QueryHandlerException
     */
    public function dispatch(object $query): mixed
    {
        $queryClass = get_class($query);

        // Check if we have a registered handler
        if (!$this->handlerRegistry->hasHandlerFor($queryClass)) {
            throw new HandlerNotFoundException(
                sprintf('No handler found for query %s', $queryClass)
            );
        }

        // Get the handler information
        $handlerInfo = $this->handlerRegistry->getHandlerFor($queryClass);

        // If we have a middleware processor, use it
        if ($this->middlewareProcessor !== null) {
            try {
                return $this->middlewareProcessor->process(
                    $this,
                    'executeHandler',
                    [$query, $handlerInfo],
                    function ($args) {
                        return $this->executeHandler(...$args);
                    }
                );
            } catch (QueryHandlerException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new QueryHandlerException(
                    sprintf('Error handling query %s: %s', $queryClass, $e->getMessage()),
                    0,
                    $e
                );
            }
        } else {
            // Otherwise, just execute the handler directly
            return $this->executeHandler($query, $handlerInfo);
        }
    }

    /**
     * Execute the handler for a query
     *
     * @param object $query The query to handle
     * @param array $handlerInfo The handler information
     * @return mixed
     * @throws \Throwable
     */
    public function executeHandler(object $query, array $handlerInfo): mixed
    {
        try {
            $handler = $this->handlerResolver->resolveHandler($handlerInfo);
            return $handler($query);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * @return QueryHandlerRegistry
     */
    public function getHandlerRegistry(): QueryHandlerRegistry
    {
        return $this->handlerRegistry;
    }
}