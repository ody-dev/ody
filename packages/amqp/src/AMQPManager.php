<?php

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Process\ProcessManager;
use Ody\Task\TaskManager;
use Throwable;

class AMQPManager
{
    /**
     * Store active consumer processes for management
     */
    private array $activeConsumerProcesses = [];

    public function __construct(
        protected MessageProcessor         $messageProcessor,
        protected TaskManager              $taskManager,
        private readonly ProcessManager    $processManager,
        private readonly ConnectionFactory $connectionFactory
    ) {}

    /**
     * Fork a consumer process using the Process system
     * The process is created now, but will wait for the server to start
     * before consuming messages
     */
    public function forkConsumerProcess(string $consumerClass, Consumer $consumerAttribute, string $connectionName = 'default'): void
    {
        $queueKey = $consumerAttribute->exchange . ':' . $consumerAttribute->queue;

        // Check if we already have a process for this queue
        if (isset($this->activeConsumerProcesses[$queueKey])) {
            $processInfo = $this->activeConsumerProcesses[$queueKey];
            $pid = $processInfo['process'];

            // Check if the process is still running
            $isRunning = $this->isPidRunning($pid);

            if ($isRunning) {
                logger()->debug("[AMQP] Consumer process for queue {$consumerAttribute->queue} already exists");
                return;
            }

            // Process is dead, remove it from tracking
            logger()->debug("[AMQP] Removing dead consumer process for queue {$consumerAttribute->queue}");
            unset($this->activeConsumerProcesses[$queueKey]);
        }

        logger()->debug("[AMQP] Forking consumer process for queue {$consumerAttribute->queue} with class {$consumerClass}");

        // Create a process for this consumer but don't instantiate the consumer yet
        $process = $this->processManager->execute(
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
                'connection_factory' => $this->connectionFactory,
            ],
            daemon: true
        );

        // Store the process for management
        $this->activeConsumerProcesses[$queueKey] = [
            'process' => $process,
            'class' => $consumerClass,
            'attribute' => $consumerAttribute,
        ];
    }

    /**
     * Produce a message
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        return $this->messageProcessor->produce($producerMessage, $connectionName);
    }

    /**
     * Check if a process with the given PID is still running
     *
     * @param int $pid Process ID to check
     * @return bool True if process is running, false otherwise
     */
    private function isPidRunning(int $pid): bool
    {
        try {
            // Using posix_kill with signal 0 just checks if the process exists
            // without actually sending a signal
            if (function_exists('posix_kill')) {
                return posix_kill($pid, 0);
            }

            // Fallback for systems without posix functions
            if (PHP_OS_FAMILY === 'Windows') {
                $output = [];
                exec("tasklist /FI \"PID eq $pid\" 2>&1", $output);
                return count($output) > 1 && str_contains($output[1], $pid);
            } else {
                $output = [];
                exec("ps -p $pid -o pid= 2>&1", $output);
                return count($output) > 0;
            }
        } catch (Throwable $e) {
            logger()->debug("[AMQP] Error checking process status: " . $e->getMessage());
            return false;
        }
    }
}