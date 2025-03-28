<?php

namespace Ody\CQRS\Enqueue;

use Enqueue\Client\ProducerInterface;
use Illuminate\Contracts\Container\Container;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Exception\QueryHandlerException;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\QueryBus as QueryBusInterface;

class EnqueueQueryBus implements QueryBusInterface
{
    /**
     * @var ProducerInterface
     */
    protected ProducerInterface $producer;

    /**
     * @var QueryHandlerRegistry
     */
    protected QueryHandlerRegistry $handlerRegistry;

    /**
     * @var QueryHandlerResolver
     */
    protected QueryHandlerResolver $handlerResolver;

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * @param ProducerInterface $producer
     * @param QueryHandlerRegistry $handlerRegistry
     * @param QueryHandlerResolver $handlerResolver
     * @param Container $container
     * @param Configuration $configuration
     */
    public function __construct(
        ProducerInterface    $producer,
        QueryHandlerRegistry $handlerRegistry,
        QueryHandlerResolver $handlerResolver,
        Container            $container,
        Configuration        $configuration
    )
    {
        $this->producer = $producer;
        $this->handlerRegistry = $handlerRegistry;
        $this->handlerResolver = $handlerResolver;
        $this->container = $container;
        $this->configuration = $configuration;
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

        // For now, we'll handle queries synchronously
        // In the future, we could add support for async queries with callbacks or futures
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