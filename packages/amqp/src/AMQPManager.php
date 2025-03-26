<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Process\ProcessManager;
use Ody\Task\Task;
use Ody\Task\TaskManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPManager
{
    public function __construct(
        private MessageProcessor $messageProcessor,
        private TaskManager    $taskManager,
        private ProcessManager $processManager,
    )
    {
    }

    /**
     * Start a consumer process using the Process system
     */
    public function startConsumerProcess(object $consumer, Consumer $consumerAttribute, string $poolName = 'default'): void
    {
        // Create a process for this consumer
        $this->processManager->execute(
            processClass: AMQPConsumerProcess::class,
            args: [
                'consumer_class' => get_class($consumer),
                'consumer_instance' => $consumer,
                'consumer_attribute' => $consumerAttribute,
                'pool_name' => $poolName,
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
    public function produce(object $producerMessage, string $poolName = 'default'): bool
    {
        return $this->messageProcessor->produce($producerMessage, $poolName);
    }
}