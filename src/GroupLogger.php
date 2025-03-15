<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Group Logger
 * Logs messages to multiple loggers at once with self-registration capabilities
 *
 * Note: The create() method is a special case that requires the LogManager,
 * so it's implemented differently from other loggers.
 */
class GroupLogger extends AbstractLogger
{
    /**
     * @var LoggerInterface[] Array of loggers
     */
    protected array $loggers = [];

    /**
     * Constructor
     *
     * @param LoggerInterface[] $loggers
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        array $loggers = [],
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);

        foreach ($loggers as $logger) {
            $this->addLogger($logger);
        }
    }

    /**
     * Create method - note that this is a special case that would typically
     * be handled directly by the LogManager since it needs access to other channels.
     *
     * This implementation is provided for API consistency, but in practice
     * the LogManager handles group creation itself.
     *
     * @param array $config
     * @return LoggerInterface
     * @throws \InvalidArgumentException
     */
    public static function create(array $config): LoggerInterface
    {
        // This is typically handled by LogManager directly
        // as it needs access to other channels
        throw new \LogicException(
            "GroupLogger must be created by the LogManager as it depends on other channels. " .
            "This method exists only for interface consistency."
        );
    }

    /**
     * Add a logger to the group
     *
     * @param LoggerInterface $logger
     * @return self
     */
    public function addLogger(LoggerInterface $logger): self
    {
        $this->loggers[] = $logger;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        $errors = [];

        foreach ($this->loggers as $index => $logger) {
            try {
                // Forward the log message to each logger
                $logger->log($level, $message, $context);
            } catch (\Throwable $e) {
                // Collect the error but don't interrupt other loggers
                $loggerClass = get_class($logger);
                $errors[] = "Logger #{$index} ({$loggerClass}) error: " . $e->getMessage();

                // Output to error_log as a fallback
                error_log("GroupLogger error with {$loggerClass}: " . $e->getMessage());
            }
        }

        // If we had errors, add them to the context for the next logger
        if (!empty($errors)) {
            $context['group_logger_errors'] = $errors;
        }
    }

    /**
     * Get all loggers in this group
     *
     * @return array
     */
    public function getLoggers(): array
    {
        return $this->loggers;
    }

    /**
     * Count the number of loggers in this group
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->loggers);
    }
}