<?php

namespace Ody\AMQP;

use Ody\Process\ProcessManager;
use Ody\Task\TaskManager;

/**
 * Modified AMQPManager with connection pooling
 */
class PooledAMQPManager extends AMQPManager
{
    /**
     * Constructor with dependency injection
     */
    public function __construct(
        protected PooledMessageProcessor $pooledMessageProcessor,
        protected TaskManager            $taskManager,
        ProcessManager                   $processManager,
        ConnectionFactory                $connectionFactory
    )
    {
        parent::__construct(
            $pooledMessageProcessor,
            $taskManager,
            $processManager,
            $connectionFactory
        );
    }

    /**
     * Produce a message using connection pooling
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        // Use the PooledMessageProcessor for producing messages
        return $this->pooledMessageProcessor->produce($producerMessage, $connectionName);
    }
}