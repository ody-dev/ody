<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Process\StandardProcess;
use Ody\Task\TaskManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;

class AMQPConsumerProcess extends StandardProcess
{
    private string $consumerClass;
    private Consumer $consumerAttribute;
    private string $poolName;
    private TaskManager $taskManager;
    private ?AMQPChannel $channel = null;
    private bool $serverReady = false;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $args, Process $worker)
    {
        parent::__construct($args, $worker);

        $this->consumerClass = $args['consumer_class'];
        $this->consumerAttribute = $args['consumer_attribute'];
        $this->poolName = $args['pool_name'];
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
            if ($this->channel) {
                try {
                    $this->channel->close();
                } catch (\Exception $e) {
                    // Ignore exceptions during shutdown
                }
            }
        });

        // Start a timer to check if the server is ready
        // This is needed because the process is forked before the server starts
        $this->waitForServerReady();
    }

    /**
     * Wait for the server to be ready before starting the consumer
     */
    private function waitForServerReady(): void
    {
        // Poll for server readiness with a timer
        // In a real implementation, you might want to use a signal or IPC mechanism
        // to be notified when the server is ready
        Timer::tick(1000, function ($timerId) {
            // Check if server is ready (you'll need to implement this check)
            if ($this->isServerReady()) {
                Timer::clear($timerId);
                $this->serverReady = true;

                // Now start the consumer in a coroutine context
                $this->startConsumer();
            }
        });
    }

    /**
     * Check if the server is ready to accept connections
     * This is a placeholder - you'll need to implement a real check
     */
    private function isServerReady(): bool
    {
        // In a real implementation, you might check a file, a shared memory flag,
        // or use another IPC mechanism to determine if the server is ready

        // You could also just wait a fixed amount of time after process start
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }

        // For now, just assume server is ready after 5 seconds
        return (time() - $startTime) > 5;
    }

    /**
     * Start the consumer once the server is ready
     */
    private function startConsumer(): void
    {
        try {
            // Create the consumer instance
            $consumer = new $this->consumerClass();

            // Now we're in a coroutine context with the server ready,
            // so it's safe to get a connection
            $connection = ConnectionManager::getConnection($this->poolName);
            $this->channel = $connection->channel();

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
                    $this->processMessage($consumer, $message);
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

            ConnectionManager::returnConnection($connection, $this->poolName);
        } catch (\Exception $e) {
            // Log the error
            error_log("AMQP Consumer process error: " . $e->getMessage());

            // If we should restart, exit with non-zero code so the process manager will restart it
            exit(1);
        }
    }

    /**
     * Process a message
     */
//    protected function processMessage(object $consumer, AMQPMessage $message): void
//    {
//        try {
//            // Use a Task to process the message
//            Task::execute(AMQPMessageTask::class, [
//                'consumer_instance' => $consumer,
//                'message_body' => $message->body,
//                'delivery_tag' => $message->getDeliveryTag(),
//                'channel' => $this->channel,
//            ]);
//        } catch (\Throwable $e) {
//            error_log("Error processing message: " . $e->getMessage());
//
//            // Reject the message
//            try {
//                $this->channel->basic_reject($message->getDeliveryTag(), false);
//            } catch (\Throwable $e) {
//                error_log("Error rejecting message: " . $e->getMessage());
//            }
//        }
//    }

    /**
     * Process messages sent to this process
     */
    protected function processMessage(string $data): ?string
    {
        // This is used for control messages to the process
        // For example, to request stats or to trigger a graceful shutdown
        $command = json_decode($data, true);

        if ($command && isset($command['action'])) {
            switch ($command['action']) {
                case 'status':
                    return json_encode([
                        'status' => 'running',
                        'consumer' => $this->consumerClass,
                        'queue' => $this->consumerAttribute->queue,
                        'server_ready' => $this->serverReady,
                    ]);

                case 'shutdown':
                    $this->running = false;
                    return json_encode(['status' => 'shutting_down']);

                case 'server_ready':
                    // A way to explicitly signal that the server is ready
                    $this->serverReady = true;
                    return json_encode(['status' => 'acknowledged']);
            }
        }

        return json_encode(['error' => 'Unknown command']);
    }
}