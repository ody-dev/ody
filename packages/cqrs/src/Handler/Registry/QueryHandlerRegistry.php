<?php

namespace Ody\CQRS\Handler\Registry;

class QueryHandlerRegistry extends HandlerRegistry
{
    /**
     * Register a query handler
     *
     * @param string $queryClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $queryClass,
        string $handlerClass,
        string $handlerMethod
    ): void
    {
        $this->register($queryClass, [
            'class' => $handlerClass,
            'method' => $handlerMethod,
        ]);
    }
}