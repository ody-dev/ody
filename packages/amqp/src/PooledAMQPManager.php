<?php

namespace Ody\AMQP;

/**
 * Modified AMQPManager with connection pooling
 */
class PooledAMQPManager extends AMQPManager
{
    /**
     * Produce a message using connection pooling
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        // Get processor with pooling
        $processor = new PooledMessageProcessor($this->taskManager);

        // Register producer classes
        foreach ($this->messageProcessor->getProducerClasses() as $class => $attribute) {
            $processor->registerProducerClass($class);
        }

        return $processor->produce($producerMessage, $connectionName);
    }
}