<?php
declare(strict_types=1);

namespace Ody\AMQP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Producer
{
    public function __construct(
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly string $type = 'direct',
        public readonly bool   $delayed = false,
    )
    {
    }
}