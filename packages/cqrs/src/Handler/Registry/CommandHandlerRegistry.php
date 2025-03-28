<?php

namespace Ody\CQRS\Handler\Registry;

class CommandHandlerRegistry extends HandlerRegistry
{
    /**
     * Register a command handler
     *
     * @param string $commandClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $commandClass,
        string $handlerClass,
        string $handlerMethod
    ): void
    {
        $this->register($commandClass, [
            'class' => $handlerClass,
            'method' => $handlerMethod,
        ]);
    }
}