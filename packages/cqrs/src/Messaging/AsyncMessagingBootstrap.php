<?php

namespace Ody\CQRS\Messaging;

use Ody\CQRS\Attributes\Async;
use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;

class AsyncMessagingBootstrap
{
    /**
     * @var array Mapping of command class names to channel info
     */
    private array $asyncHandlers = [];

    /**
     * @param CommandBusInterface $commandBus
     * @param MessageBroker $messageBroker
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly MessageBroker       $messageBroker,
        private readonly LoggerInterface     $logger
    )
    {
    }

    /**
     * Scan for and register async command handlers
     *
     * @param array $handlerPaths Paths to scan for handlers
     * @return void
     */
    public function registerAsyncHandlers(array $handlerPaths): void
    {
        foreach ($handlerPaths as $path) {
            $this->scanPath($path);
        }

        foreach ($this->asyncHandlers as $commandClass => $channelInfo) {
            $this->registerAsyncHandlerForCommand($commandClass, $channelInfo['channel']);
        }

        $workerId = getmypid();
        $this->logger->debug(sprintf(
            "[Worker {$workerId}] Registered %d async command handlers",
            count($this->asyncHandlers)
        ));
    }

    /**
     * Scan a directory path for async command handlers
     *
     * @param string $path
     * @return void
     */
    private function scanPath(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $directoryIterator = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);
        $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i');

        foreach ($phpFiles as $phpFile) {
            $this->processFile($phpFile->getRealPath());
        }
    }

    /**
     * Process a PHP file to find async command handlers
     *
     * @param string $filePath
     * @return void
     */
    private function processFile(string $filePath): void
    {
        // Get the file contents
        $fileContent = file_get_contents($filePath);

        // Extract the namespace and class name
        preg_match('/namespace\s+([^;]+);/', $fileContent, $namespaceMatches);
        preg_match('/class\s+([^\s{]+)/', $fileContent, $classNameMatches);

        if (empty($namespaceMatches[1]) || empty($classNameMatches[1])) {
            return;
        }

        $namespace = $namespaceMatches[1];
        $className = $classNameMatches[1];
        $fullyQualifiedClassName = $namespace . '\\' . $className;

        try {
            // Load the class
            if (!class_exists($fullyQualifiedClassName)) {
                return;
            }

            $reflectionClass = new ReflectionClass($fullyQualifiedClassName);

            // Check each method for CommandHandler and Async attributes
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $commandHandlerAttributes = $method->getAttributes(CommandHandler::class);
                $asyncAttributes = $method->getAttributes(Async::class);

                if (empty($commandHandlerAttributes) || empty($asyncAttributes)) {
                    continue;
                }

                // Get the command class from the method parameter
                $params = $method->getParameters();
                if (empty($params) || !$params[0]->hasType()) {
                    continue;
                }

                $commandClass = $params[0]->getType()->getName();
                $asyncAttribute = $asyncAttributes[0]->newInstance();

                // Register the async handler info
                $this->asyncHandlers[$commandClass] = [
                    'channel' => $asyncAttribute->channel,
                    'handler' => [
                        'class' => $fullyQualifiedClassName,
                        'method' => $method->getName()
                    ]
                ];

                $workerId = getmypid();
                $this->logger->debug("[Worker {$workerId}] Found async handler for {$commandClass} on channel {$asyncAttribute->channel}");
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error processing file {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Register an async handler for a command
     *
     * @param string $commandClass
     * @param string $channel
     * @return void
     */
    private function registerAsyncHandlerForCommand(string $commandClass, string $channel): void
    {
        // Get the original handler info before replacing it
        $originalHandlerInfo = $this->commandBus->getHandlerRegistry()->getHandlerFor($commandClass);

        // Store the original handler info with the command class
        $this->asyncHandlers[$commandClass]['handler_info'] = $originalHandlerInfo;

        // Register a custom handler that sends the command to the message broker
        $this->commandBus->registerHandler(
            $commandClass,
            function ($command) use ($channel) {
                return $this->messageBroker->send($channel, $command);
            }
        );

        $workerId = getmypid();
        $this->logger->debug("[Worker {$workerId}] Registered async handler for {$commandClass} on channel {$channel}");
    }

    /**
     * Clear all registered async handlers
     *
     * @return void
     */
    public function clearAsyncHandlers(): void
    {
        foreach (array_keys($this->asyncHandlers) as $commandClass) {
            $this->commandBus->unregisterHandler($commandClass);
        }

        $this->asyncHandlers = [];
        $this->logger->debug('Cleared async command handlers');
    }

    public function getOriginalHandlerInfo(string $commandClass): ?array
    {
        return $this->asyncHandlers[$commandClass]['handler_info'] ?? null;
    }
}