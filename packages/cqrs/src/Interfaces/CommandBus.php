<?php

namespace Ody\CQRS\Interfaces;

use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;

interface CommandBus
{
    public function dispatch(object $command): void;

    public function getHandlerRegistry(): CommandHandlerRegistry;
}