<?php

namespace Ody\CQRS\Handler\Resolver;

use Ody\Container\Container;
use Ody\CQRS\Bus\EventBus;

class CommandHandlerResolver extends HandlerResolver
{
    /**
     * @param Container $container
     */
    public function __construct(
        Container $container
    )
    {
        parent::__construct($container);
    }

    /**
     * Resolves a command handler from the handler info
     * Injects EventBus as a second parameter if the handler expects it
     *
     * @param array $handlerInfo
     * @return callable
     */
    public function resolveHandler(array $handlerInfo): callable
    {
        $handlerClass = $handlerInfo['class'];
        $handlerMethod = $handlerInfo['method'];

        $handler = $this->container->make($handlerClass);

        // Check if the method accepts EventBus as a second parameter
        $reflection = new \ReflectionMethod($handlerClass, $handlerMethod);
        $parameters = $reflection->getParameters();

        $expectsEventBus = count($parameters) > 1 &&
            $parameters[1]->getType() &&
            is_a(EventBus::class, $parameters[1]->getType()->getName(), true);

        if ($expectsEventBus) {
            return function ($command) use ($handler, $handlerMethod) {
                // Get the EventBus lazily from the container when needed
                $eventBus = $this->container->make(EventBus::class);
                return $handler->$handlerMethod($command, $eventBus);
            };
        }

        return function ($command) use ($handler, $handlerMethod) {
            return $handler->$handlerMethod($command);
        };
    }
}