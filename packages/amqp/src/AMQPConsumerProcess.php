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
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
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
    private bool $reconnecting = false;
    private ?int $heartbeatTimerId = null;
    private int $lastActivityTime = 0;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_ATTEMPTS = 10;
    private const RECONNECT_DELAY_MS = 5000; // 5 seconds
    private const CONNECTION_HEALTH_CHECK_INTERVAL_MS = 10000; // 10 seconds

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
        $this->lastActivityTime = time();
    }

    /**
     * The main process logic
     */
    public function handle(): void
    {
        // Set up signal handlers for graceful shutdown
        pcntl_signal(SIGTERM, function () {
            $this->running = false;
            $this->cleanupResources();
        });

        // Wait a bit before starting the consumer to ensure the server is ready
        Timer::after(5000, function () {
            $this->startConsumer();
        });
    }

    /**
     * Start the consumer with direct connection
     */
    private function startConsumer(): void
    {
        try {
            error_log("[AMQP] Starting consumer for {$this->consumerAttribute->queue}");

            // Set up the connection and channel
            $this->setupConnection();

            // Start connection health check timer
            $this->startConnectionHealthCheck();

            // Keep the process running
            while ($this->running) {
                // Process signals
                pcntl_signal_dispatch();

                if (!$this->reconnecting) {
                    try {
                        // Process any messages in the queue with a shorter timeout
                        $this->channel->wait(null, true, 0.5);
                        $this->lastActivityTime = time(); // Update activity time after successful wait
                    } catch (AMQPTimeoutException $e) {
                        // This is normal when no messages are available - just continue
                    } catch (AMQPConnectionClosedException | AMQPChannelClosedException $e) {
                        error_log("[AMQP] Connection or channel closed: " . $e->getMessage());
                        $this->handleDisconnect();
                    } catch (Throwable $e) {
                        error_log("[AMQP] Error during channel wait: " . $e->getMessage());
                        $this->handleDisconnect();
                    }
                }

                // Yield to other coroutines
                Coroutine::sleep(0.01);
            }

            // Clean up resources
            $this->cleanupResources();

        } catch (Exception $e) {
            // Log the error with detailed stack trace
            error_log("[AMQP] Consumer process startup error: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // If we should restart, exit with non-zero code so the process manager will restart it
            exit(1);
        }
    }

    /**
     * Set up the AMQP connection and channel
     */
    private function setupConnection(): void
    {
        try {
            error_log("[AMQP] Setting up connection for {$this->consumerAttribute->queue}");

            // Create the consumer instance
            $consumer = new $this->consumerClass();

            // Create direct connection with enhanced heartbeat/timeout settings
            $this->connection = AMQP::createConnection($this->connectionName);

            error_log("[AMQP] Connection established, creating channel");
            $this->channel = $this->connection->channel();

            // Set QoS if specified
            $prefetchCount = $this->consumerAttribute->prefetchCount ?? 10;
            error_log("[AMQP] Setting prefetch count to {$prefetchCount}");
            $this->channel->basic_qos(0, $prefetchCount, false);

            // Declare exchange
            error_log("[AMQP] Declaring exchange {$this->consumerAttribute->exchange}");
            $this->channel->exchange_declare(
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->type,
                false,
                true,
                false
            );

            // Declare queue
            error_log("[AMQP] Declaring queue {$this->consumerAttribute->queue}");
            $this->channel->queue_declare(
                $this->consumerAttribute->queue,
                false,
                true,
                false,
                false
            );

            // Bind queue to exchange
            error_log("[AMQP] Binding queue to exchange with routing key {$this->consumerAttribute->routingKey}");
            $this->channel->queue_bind(
                $this->consumerAttribute->queue,
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->routingKey
            );

            // Set up consumer callback
            error_log("[AMQP] Setting up consumer callback");
            $this->channel->basic_consume(
                $this->consumerAttribute->queue,
                '', // consumer tag
                false, // no local
                false, // no ack
                false, // exclusive
                false, // no wait
                function (AMQPMessage $message) use ($consumer) {
                    // Process the message
                    $this->processAmqpMessage($consumer, $message);
                }
            );

            error_log("[AMQP] Consumer setup complete for {$this->consumerAttribute->queue}");
            $this->reconnecting = false;
            $this->reconnectAttempts = 0;
            $this->lastActivityTime = time(); // Reset activity time

        } catch (Throwable $e) {
            error_log("[AMQP] Error during connection setup: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Clean up any partial resources
            $this->cleanupResources();

            // Schedule reconnect if allowed
            $this->handleDisconnect();
        }
    }

    /**
     * Start the connection health check timer
     */
    private function startConnectionHealthCheck(): void
    {
        // Cancel any existing timer
        if ($this->heartbeatTimerId !== null) {
            Timer::clear($this->heartbeatTimerId);
        }

        // Start a new health check timer
        $this->heartbeatTimerId = Timer::tick(self::CONNECTION_HEALTH_CHECK_INTERVAL_MS, function () {
            if ($this->reconnecting) {
                return; // Skip health check during reconnection
            }

            try {
                // Check for connection staleness (inactivity timeout)
                $inactiveSeconds = time() - $this->lastActivityTime;
                if ($inactiveSeconds > 30) { // 30 second inactivity threshold
                    error_log("[AMQP] Connection inactive for {$inactiveSeconds}s, performing health check");

                    // Try to check connection is alive
                    if (!$this->isConnectionHealthy()) {
                        error_log("[AMQP] Connection appears stale, forcing reconnect");
                        $this->handleDisconnect();
                        return;
                    }

                    // If we got here, connection is still good, update activity time
                    $this->lastActivityTime = time();
                    error_log("[AMQP] Connection health check passed for {$this->consumerAttribute->queue}");
                }
            } catch (Throwable $e) {
                error_log("[AMQP] Error during health check: " . $e->getMessage());
                $this->handleDisconnect();
            }
        });
    }

    /**
     * Alert about persistent connection failures
     * This can be extended to send alerts to monitoring systems, email, etc.
     */
    private function alertConnectionFailure(): void
    {
        $errorMessage = sprintf(
            "[AMQP] CRITICAL: Consumer for queue %s (class %s) has failed to connect after %d attempts",
            $this->consumerAttribute->queue,
            $this->consumerClass,
            self::MAX_RECONNECT_ATTEMPTS
        );

        // Log the critical error
        error_log($errorMessage);

        // implement additional alerting mechanisms:
        $alertsFile = '/tmp/amqp_connection_alerts.log';
        file_put_contents(
            $alertsFile,
            date('[Y-m-d H:i:s] ') . $errorMessage . PHP_EOL,
            FILE_APPEND
        );

        // send an alert there
        /*
        try {
            $monitoringUrl = '/api/alerts';
            $client = new \GuzzleHttp\Client();
            $client->post($monitoringUrl, [
                'json' => [
                    'severity' => 'critical',
                    'service' => 'amqp',
                    'message' => $errorMessage,
                    'queue' => $this->consumerAttribute->queue,
                    'consumer' => $this->consumerClass,
                    'attempts' => self::MAX_RECONNECT_ATTEMPTS,
                    'timestamp' => time()
                ]
            ]);
        } catch (Throwable $e) {
            error_log("[AMQP] Failed to send alert to monitoring service: " . $e->getMessage());
        }
        */
    }

    /**
     * Handle a disconnection event
     */
    private function handleDisconnect(): void
    {
        if ($this->reconnecting) {
            return; // Already in reconnect process
        }

        $this->reconnecting = true;
        $this->reconnectAttempts++;

        // Clean up existing resources
        $this->cleanupResources(false); // Don't cancel timers during reconnect

        // Handle max reconnect attempts
        if ($this->reconnectAttempts > self::MAX_RECONNECT_ATTEMPTS) {
            error_log("[AMQP] Max reconnect attempts reached for {$this->consumerAttribute->queue}, implementing longer backoff");

            // Instead of exiting, we'll implement a longer backoff and keep trying
            // We'll reset the counter but use a longer delay
            $this->reconnectAttempts = 1;
            $delay = self::RECONNECT_DELAY_MS * 10; // Much longer delay after max attempts

            error_log("[AMQP] Extended reconnection backoff: waiting {$delay}ms before next attempt");

            // Optionally, you could implement an alerting mechanism here
            // For example, sending an alert to a monitoring system
            $this->alertConnectionFailure();
        } else {
            // Normal exponential backoff
            $delay = self::RECONNECT_DELAY_MS * min($this->reconnectAttempts, 5); // Exponential backoff capped at 5x
            error_log("[AMQP] Scheduling reconnect attempt {$this->reconnectAttempts} in {$delay}ms");
        }

        // Schedule reconnect
        Timer::after($delay, function () {
            try {
                error_log("[AMQP] Attempting to reconnect...");
                $this->setupConnection();
            } catch (Throwable $e) {
                error_log("[AMQP] Reconnect failed: " . $e->getMessage());
                $this->reconnecting = false; // Reset flag so next health check can try again
            }
        });
    }

    /**
     * Process an AMQP message
     */
    private function processAmqpMessage(object $consumer, AMQPMessage $message): void
    {
        try {
            $deliveryTag = $message->getDeliveryTag();
            error_log("[AMQP] Processing message with delivery tag: {$deliveryTag}");

            // Process the message directly for now instead of using a Task
            $data = json_decode($message->body, true) ?: [];
            error_log("[AMQP] Message content: " . json_encode($data));

            $result = $consumer->consumeMessage($data, $message);
            error_log("[AMQP] Consumer returned result: {$result->name}");

            // Handle the result
            switch ($result) {
                case Result::ACK:
                    error_log("[AMQP] Acknowledging message {$deliveryTag}");
                    $this->channel->basic_ack($deliveryTag);
                    break;

                case Result::NACK:
                    error_log("[AMQP] Negative acknowledging message {$deliveryTag}");
                    $this->channel->basic_nack($deliveryTag);
                    break;

                case Result::REQUEUE:
                    error_log("[AMQP] Rejecting and requeuing message {$deliveryTag}");
                    $this->channel->basic_reject($deliveryTag, true);
                    break;

                case Result::DROP:
                    error_log("[AMQP] Dropping message {$deliveryTag}");
                    $this->channel->basic_reject($deliveryTag, false);
                    break;
            }

            // Update activity timestamp
            $this->lastActivityTime = time();

        } catch (AMQPConnectionClosedException | AMQPChannelClosedException $e) {
            error_log("[AMQP] Connection/channel closed during message processing: " . $e->getMessage());
            $this->handleDisconnect();
        } catch (Throwable $e) {
            error_log("[AMQP] Error processing message: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Reject the message
            try {
                error_log("[AMQP] Rejecting message due to processing error");
                $this->channel->basic_reject($message->getDeliveryTag(), false);
            } catch (Throwable $e) {
                error_log("[AMQP] Error rejecting message: " . $e->getMessage());
                // Force reconnect on any channel operation failure, but only if not already reconnecting
                if (!$this->reconnecting) {
                    $this->handleDisconnect();
                }
            }
        }
    }

    /**
     * Clean up resources
     */
    private function cleanupResources(bool $clearTimers = true): void
    {
        // Close channel
        if ($this->channel && $this->channel->is_open()) {
            try {
                error_log("[AMQP] Closing channel");
                $this->channel->close();
            } catch (Throwable $e) {
                error_log("[AMQP] Error closing channel: " . $e->getMessage());
            }
        }
        $this->channel = null;

        // Close connection
        if ($this->connection && $this->connection->isConnected()) {
            try {
                error_log("[AMQP] Closing connection");
                $this->connection->close();
            } catch (Throwable $e) {
                error_log("[AMQP] Error closing connection: " . $e->getMessage());
            }
        }
        $this->connection = null;

        // Clear timers if requested
        if ($clearTimers && $this->heartbeatTimerId !== null) {
            Timer::clear($this->heartbeatTimerId);
            $this->heartbeatTimerId = null;
        }
    }

    /**
     * Check if the connection and channel are healthy
     */
    private function isConnectionHealthy(): bool
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            return false;
        }

        if (!$this->channel || !$this->channel->is_open()) {
            return false;
        }

        // Perform a lightweight operation on the channel to verify it's responsive
        try {
            // This is a very lightweight operation that doesn't affect the channel
            $this->channel->basic_qos($this->channel->getQos()[0], $this->channel->getQos()[1], false);
            return true;
        } catch (Throwable $e) {
            error_log("[AMQP] Channel health check failed: " . $e->getMessage());
            return false;
        }
    }
}