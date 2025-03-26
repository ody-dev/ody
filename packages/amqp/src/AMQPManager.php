<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\Result;
use Ody\Process\ProcessManager;
use Ody\Task\TaskManager;

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
     * Start a consumer process using the existing Process system
     */
    public function startConsumerProcess(object $consumer, Consumer $consumerAttribute, string $poolName = 'default'): void
    {
        $this->processManager->createProcess(
            name: 'amqp_consumer_' . $consumerAttribute->queue,
            callback: function () use ($consumer, $consumerAttribute, $poolName) {
                $connection = ConnectionManager::getConnection($poolName);
                $channel = $connection->channel();

                // Set QoS if specified
                if ($consumerAttribute->prefetchCount !== null) {
                    $channel->basic_qos(0, $consumerAttribute->prefetchCount, false);
                }

                // Declare exchange
                $channel->exchange_declare(
                    $consumerAttribute->exchange,
                    $consumerAttribute->type,
                    false,
                    true,
                    false
                );

                // Declare queue
                $channel->queue_declare(
                    $consumerAttribute->queue,
                    false,
                    true,
                    false,
                    false
                );

                // Bind queue to exchange
                $channel->queue_bind(
                    $consumerAttribute->queue,
                    $consumerAttribute->exchange,
                    $consumerAttribute->routingKey
                );

                // Set up consumer callback
                $channel->basic_consume(
                    $consumerAttribute->queue,
                    '',
                    false,
                    false,
                    false,
                    false,
                    function ($message) use ($consumer, $channel) {
                        // Process the message using a Swoole Task
                        $this->taskManager->asyncTask(function () use ($consumer, $message, $channel) {
                            $data = json_decode($message->body, true);
                            $result = $consumer->consumeMessage($data, $message);

                            switch ($result) {
                                case Result::ACK:
                                    $channel->basic_ack($message->getDeliveryTag());
                                    break;
                                case Result::NACK:
                                    $channel->basic_nack($message->getDeliveryTag());
                                    break;
                                case Result::REQUEUE:
                                    $channel->basic_reject($message->getDeliveryTag(), true);
                                    break;
                                case Result::DROP:
                                    $channel->basic_reject($message->getDeliveryTag(), false);
                                    break;
                            }
                        });
                    }
                );

                // Keep the process running
                while (true) {
                    $channel->wait();
                }
            },
            workerId: $consumerAttribute->nums
        );
    }
}