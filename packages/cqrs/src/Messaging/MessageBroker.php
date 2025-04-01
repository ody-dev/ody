<?php

namespace Ody\CQRS\Messaging;

interface MessageBroker
{
    /**
     * Send a message to the specified channel
     *
     * @param string $channel The channel/queue to send to
     * @param object $message The message to send
     * @return bool Success state
     */
    public function send(string $channel, object $message): bool;

    /**
     * Set up a consumer for the specified channel
     *
     * @param string $channel The channel/queue to consume from
     * @param callable $handler The handler for received messages
     * @return bool Success state
     */
    public function receive(string $channel, callable $handler): bool;
}