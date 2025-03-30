<?php

namespace Ody\CQRS\Interfaces;

use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;

interface QueryBusInterface
{
    public function dispatch(object $query): mixed;

    public function getHandlerRegistry(): QueryHandlerRegistry;
}