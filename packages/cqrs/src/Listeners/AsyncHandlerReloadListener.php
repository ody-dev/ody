<?php

namespace Ody\CQRS\Listeners;

use Ody\CQRS\Messaging\AsyncMessagingBootstrap;
use Ody\Framework\Events\CodeReloaded;
use Ody\Support\Config;
use Psr\Log\LoggerInterface;

class AsyncHandlerReloadListener
{
    /**
     * @param AsyncMessagingBootstrap $asyncBootstrap
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AsyncMessagingBootstrap $asyncBootstrap,
        private readonly Config                  $config,
        private readonly LoggerInterface         $logger
    )
    {
    }

    /**
     * Handle the code reloaded event
     *
     * @param CodeReloaded $event
     * @return void
     */
    public function handle(CodeReloaded $event): void
    {
        // Skip if async messaging is disabled or components missing
        if (!$this->config->get('messaging.async.enabled', false) ||
            !class_exists('Ody\AMQP\AMQP')) {
            return;
        }

        $this->logger->info('Rebuilding async command handlers after code reload');

        // Clear existing handler registrations
        $this->asyncBootstrap->clearAsyncHandlers();

        // Rebuild the handler mappings
        $handlerPaths = $this->config->get('cqrs.handler_paths', []);
        $this->asyncBootstrap->registerAsyncHandlers($handlerPaths);
    }
}