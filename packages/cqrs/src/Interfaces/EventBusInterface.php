<?php

namespace Ody\CQRS\Interfaces;

interface EventBusInterface
{
    public function publish(object $event): void;
}