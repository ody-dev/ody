<?php

namespace Ody\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use Ody\AMQP\Attributes\Consumer;
use Ody\Process\ProcessManager;
use Ody\Task\Task;
use Ody\Task\TaskManager;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPManager
{
    public function __construct(
        private MessageProcessor $messageProcessor,
        private TaskManager      $taskManager,
        private ProcessManager   $processManager,
    )
    {
    }

    /**
     * Fork a consumer process using the Process system
     * The process is created now, but will wait for the server to start
     * before consuming messages
     */
    public function forkConsumerProcess(string $consumerClass, Consumer $consumerAttribute, string $connectionName = 'default'): void
    {
        // Create a process for this consumer but don't instantiate the consumer yet
        $this->processManager->execute(
            processClass: AMQPConsumerProcess::class,
            args: [
                'consumer_class' => $consumerClass,
                'consumer_attribute' => $consumerAttribute,
                'connection_name' => $connectionName,
                'exchange' => $consumerAttribute->exchange,
                'routing_key' => $consumerAttribute->routingKey,
                'queue' => $consumerAttribute->queue,
                'type' => $consumerAttribute->type,
                'prefetch_count' => $consumerAttribute->prefetchCount,
                'task_manager' => $this->taskManager,
            ],
            daemon: true
        );
    }

    /**
     * Process a message using a task
     */
    public function processMessage(object $consumer, AMQPMessage $message, AMQPChannel $channel): void
    {
        // Create a task to process the message
        Task::execute(AMQPMessageTask::class, [
            'consumer' => $consumer,
            'message' => $message,
            'channel' => $channel,
        ], Task::PRIORITY_HIGH);
    }

    /**
     * Produce a message
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        return $this->messageProcessor->produce($producerMessage, $connectionName);
    }
}