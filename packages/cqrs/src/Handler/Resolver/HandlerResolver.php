<?php

namespace Ody\CQRS\Handler\Resolver;

use Ody\Container\Container;

abstract class HandlerResolver
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves a handler from the handler info
     *
     * @param array $handlerInfo
     * @return callable
     */
    public function resolveHandler(array $handlerInfo): callable
    {
        $handlerClass = $handlerInfo['class'];
        $handlerMethod = $handlerInfo['method'];

        $handler = $this->container->make($handlerClass);

        return function ($message) use ($handler, $handlerMethod) {
            return $handler->$handlerMethod($message);
        };
    }
}