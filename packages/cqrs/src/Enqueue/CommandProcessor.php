<?php

namespace Ody\CQRS\Enqueue;

use Enqueue\Client\TopicSubscriberInterface;
use Enqueue\Consumption\Result;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Ody\Container\Container;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;

class CommandProcessor implements Processor, TopicSubscriberInterface
{
    public function __construct(
        private readonly CommandHandlerRegistry $registry,
        private readonly CommandHandlerResolver $resolver,
        private readonly Container              $container
    )
    {
    }

    public static function getSubscribedTopics(): array
    {
        return ['commands'];
    }

    public function process(Message $message, Context $context): string
    {
        $data = json_decode($message->getBody(), true);
        $commandClass = $message->getProperty('command_class');

        // Recreate the command from the serialized data
        $command = new $commandClass(...$data);

        try {
            // Get the handler info
            $handlerInfo = $this->registry->getHandlerFor($commandClass);

            // Resolve and execute the handler
            $handler = $this->resolver->resolveHandler($handlerInfo);
            $handler($command);

            return Result::ACK;
        } catch (\Throwable $e) {
            // Log the error
            error_log("Error processing command: " . $e->getMessage());

            // Decide if we want to requeue or reject the message
            return Result::REJECT;
        }
    }
}