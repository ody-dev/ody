<?php

namespace Ody\CQRS\Interfaces;

interface EventBus
{
    public function publish(object $event): void;
}