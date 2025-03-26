<?php

declare(strict_types=1);

namespace Ody\AMQP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Consumer
{
    public function __construct(
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly string $queue = '',
        public readonly string $type = 'direct',
        public readonly int    $nums = 1,
        public readonly bool   $enable = true,
        public readonly ?int   $prefetchCount = null,
    )
    {
    }
}