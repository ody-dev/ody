<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Process\StandardProcess;
use Ody\Task\Task;
use Ody\Task\TaskManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Coroutine;
use Swoole\Process;

/**
 * Process that runs an AMQP consumer
 */
class AMQPConsumerProcess extends StandardProcess
{
    private object $consumer;
    private Consumer $consumerAttribute;
    private string $poolName;
    private TaskManager $taskManager;
    private ?AMQPChannel $channel = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $args, Process $worker)
    {
        parent::__construct($args, $worker);

        $this->consumer = $args['consumer_instance'];
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

        // Since this is a Swoole process, we need to enable coroutines explicitly
        Coroutine\run(function () {
            try {
                // Now we're in a coroutine context, so it's safe to get a connection
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
                    function (AMQPMessage $message) {
                        // Process the message using a Task
                        $this->processMessageWithTask($message);
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
                // Otherwise just exit gracefully
                exit(1);
            }
        });
    }

    /**
     * Process a message using the Task system
     */
    private function processMessageWithTask(AMQPMessage $message): void
    {
        // Use the task system to process the message
        Task::execute(AMQPMessageTask::class, [
            'consumer_class' => get_class($this->consumer),
            'message_body' => $message->body,
            'delivery_tag' => $message->getDeliveryTag(),
            'channel' => $this->channel,
        ]);
    }

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
                        'consumer' => get_class($this->consumer),
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