<?php

namespace Ody\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Async
{
    /**
     * @param string $channel The message channel/queue name for this async command
     */
    public function __construct(
        public readonly string $channel
    )
    {
    }
}