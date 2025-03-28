<?php

namespace Ody\CQRS\Interfaces;

interface QueryBus
{
    public function dispatch(object $query): mixed;
}