<?php

namespace Ody\CQRS\Interfaces;

interface ProducerInterface
{
    /**
     * Send a command to the queue
     *
     * @param string $topic
     * @param object $command
     * @return void
     */
    public function sendCommand(string $topic, object $command): void;

    /**
     * Send an event to the queue
     *
     * @param string $topic
     * @param object $event
     * @return void
     */
    public function sendEvent(string $topic, object $event): void;

    /**
     * Send a query to the queue
     *
     * @param string $topic
     * @param object $query
     * @return string Message ID for retrieving the result
     */
    public function sendQuery(string $topic, object $query): string;

    /**
     * Check if a query result is available
     *
     * @param string $messageId
     * @return bool
     */
    public function hasQueryResult(string $messageId): bool;

    /**
     * Get a query result
     *
     * @param string $messageId
     * @param int $timeout Timeout in milliseconds
     * @return mixed
     */
    public function getQueryResult(string $messageId, int $timeout = 5000): mixed;
}