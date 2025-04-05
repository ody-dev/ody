<?php

namespace App\Middleware;

use Ody\CQRS\Middleware\After;
use Ody\CQRS\Middleware\AfterThrowing;
use Ody\CQRS\Middleware\Before;

/**
 * Example middleware class for logging
 */
class LoggingMiddleware
{
    /**
     * Log the command before it's handled
     *
     * @param object $command The command being dispatched
     */
    #[Before(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
    public function logBeforeCommand(object $command): void
    {
        logger()->debug('Processing command: ' . get_class($command), [
            'command_id' => $command->id ?? null,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Log the query after it's handled
     *
     * @param mixed $result The result from the query
     * @param array $args The original arguments
     * @return mixed The unmodified result
     */
    #[After(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
    public function logAfterQuery(mixed $result, array $args): mixed
    {
        $query = $args[0] ?? null;

        if ($query) {
            logger()->debug('Query processed: ' . get_class($query), [
                'query_id' => $query->id ?? null,
                'result_type' => gettype($result),
                'timestamp' => microtime(true),
            ]);
        }

        return $result;
    }

    /**
     * Log any exceptions thrown during event handling
     *
     * @param \Throwable $exception The exception that was thrown
     * @param array $args The original arguments
     */
    #[AfterThrowing(pointcut: "Ody\\CQRS\\Bus\\EventBus::executeHandlers")]
    public function logEventException(\Throwable $exception, array $args): void
    {
        $event = $args[0] ?? null;

        if ($event) {
            logger()->error('Error handling event: ' . get_class($event), [
                'event_id' => $event->id ?? null,
                'error' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}


