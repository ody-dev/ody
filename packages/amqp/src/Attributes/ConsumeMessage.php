<?php

declare(strict_types=1);

namespace Ody\AMQP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ConsumeMessage
{
}