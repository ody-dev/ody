<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Exception;
use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\Result;
use Ody\Process\StandardProcess;
use Ody\Task\TaskManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

class AMQPConsumerProcess extends StandardProcess
{
    private string $consumerClass;
    private Consumer $consumerAttribute;
    private string $connectionName;
    private TaskManager $taskManager;
    private ?AMQPChannel $channel = null;
    private ?AMQPStreamConnection $connection = null;
    private bool $serverReady = false;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $args, Process $worker)
    {
        parent::__construct($args, $worker);

        $this->consumerClass = $args['consumer_class'];
        $this->consumerAttribute = $args['consumer_attribute'];
        $this->connectionName = $args['connection_name'];
        $this->taskManager = $args['task_manager'];
    }

    /**
     * The main process logic
     */
    public function handle(): void
    {
        // Set up signal handlers for graceful shutdown
        pcntl_signal(SIGTERM, function () {
            $this->running = false;

            // Close channel and connection if they're open
            if ($this->channel && $this->channel->is_open()) {
                try {
                    $this->channel->close();
                } catch (Exception $e) {
                    // Ignore exceptions during shutdown
                }
            }

            if ($this->connection && $this->connection->isConnected()) {
                try {
                    $this->connection->close();
                } catch (Exception $e) {
                    // Ignore exceptions during shutdown
                }
            }
        });

        // Wait a bit before starting the consumer to ensure the server is ready
        Timer::after(10000, function () {
            $this->startConsumer();
        });
    }

    /**
     * Start the consumer with direct connection
     */
    private function startConsumer(): void
    {
        try {
            // Create the consumer instance
            $consumer = new $this->consumerClass();

            // Create direct connection
            $this->connection = AMQP::createConnection($this->connectionName);
            $this->channel = $this->connection->channel();

            // Set QoS if specified
            if ($this->consumerAttribute->prefetchCount !== null) {
                $this->channel->basic_qos(0, $this->consumerAttribute->prefetchCount, false);
            }

            // Declare exchange
            $this->channel->exchange_declare(
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->type,
                false,
                true,
                false
            );

            // Declare queue
            $this->channel->queue_declare(
                $this->consumerAttribute->queue,
                false,
                true,
                false,
                false
            );

            // Bind queue to exchange
            $this->channel->queue_bind(
                $this->consumerAttribute->queue,
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->routingKey
            );

            // Set up consumer callback
            $this->channel->basic_consume(
                $this->consumerAttribute->queue,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($consumer) {
                    // Process the message
                    $this->processAmqpMessage($consumer, $message);
                }
            );

            // Keep the process running
            while ($this->running) {
                // Process signals
                pcntl_signal_dispatch();

                // Process any messages in the queue
                $this->channel->wait(null, true, 1);

                // Yield to other coroutines
                Coroutine::sleep(0.001);
            }

            // Clean up resources
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }

            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Exception $e) {
            // Log the error with detailed stack trace
            error_log("AMQP Consumer process error: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // If we should restart, exit with non-zero code so the process manager will restart it
            exit(1);
        }
    }

    private function processAmqpMessage(object $consumer, AMQPMessage $message): void
    {
        try {
            // Process the message directly for now instead of using a Task
            // This is simpler and avoids potential issues with task system
            $data = json_decode($message->body, true) ?: [];
            $result = $consumer->consumeMessage($data, $message);

            // Handle the result
            switch ($result) {
                case Result::ACK:
                    $this->channel->basic_ack($message->getDeliveryTag());
                    break;

                case Result::NACK:
                    $this->channel->basic_nack($message->getDeliveryTag());
                    break;

                case Result::REQUEUE:
                    $this->channel->basic_reject($message->getDeliveryTag(), true);
                    break;

                case Result::DROP:
                    $this->channel->basic_reject($message->getDeliveryTag(), false);
                    break;
            }

        } catch (Throwable $e) {
            error_log("Error processing message: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Reject the message
            try {
                $this->channel->basic_reject($message->getDeliveryTag(), false);
            } catch (Throwable $e) {
                error_log("Error rejecting message: " . $e->getMessage());
            }
        }
    }

    /**
     * Process IPC messages sent to this process
     */
    protected function processMessage(string $data): ?string
    {
        // This is used for control messages to the process
        $command = json_decode($data, true);

        if ($command && isset($command['action'])) {
            switch ($command['action']) {
                case 'status':
                    return json_encode([
                        'status' => 'running',
                        'consumer' => $this->consumerClass,
                        'queue' => $this->consumerAttribute->queue,
                    ]);

                case 'shutdown':
                    $this->running = false;
                    return json_encode(['status' => 'shutting_down']);
            }
        }

        return json_encode(['error' => 'Unknown command']);
    }
}