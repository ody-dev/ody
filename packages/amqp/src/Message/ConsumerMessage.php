<?php

declare(strict_types=1);

namespace Ody\AMQP\Message;
abstract class ConsumerMessage
{
    /**
     * Process the received message
     *
     * @param array<string, mixed> $data
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     * @return Result
     */
    abstract public function consumeMessage(array $data, \PhpAmqpLib\Message\AMQPMessage $message): Result;
}