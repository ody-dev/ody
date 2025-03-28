<?php

namespace Ody\CQRS\Interfaces;

interface CommandBus
{
    public function dispatch(object $command): void;
}