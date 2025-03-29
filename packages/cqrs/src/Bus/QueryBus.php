<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Exception\QueryHandlerException;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\QueryBus as QueryBusInterface;

class QueryBus implements QueryBusInterface
{
    public function __construct(
        private QueryHandlerRegistry $handlerRegistry,
        private QueryHandlerResolver $handlerResolver
    )
    {
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

        // Handle directly
        try {
            $handler = $this->handlerResolver->resolveHandler($handlerInfo);
            return $handler($query);
        } catch (\Throwable $e) {
            throw new QueryHandlerException(
                sprintf('Error handling query %s: %s', $queryClass, $e->getMessage()),
                0,
                $e
            );
        }
    }
}