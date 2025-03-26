<?php

declare(strict_types=1);

namespace Ody\AMQP\Message;

enum Result: string
{
    case ACK = 'ack';
    case NACK = 'nack';
    case REQUEUE = 'requeue';
    case DROP = 'drop';
}