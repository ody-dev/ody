<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;

use Ody\Logger\Formatters\FormatterInterface;
use Ody\Logger\Formatters\LineFormatter;
use Psr\Log\LogLevel;

/**
 * Abstract Logger Implementation
 * Base logger class that provides common functionality for all loggers
 */
abstract class AbstractLogger extends \Psr\Log\AbstractLogger implements LoggerInterface
{
    /**
     * @var string Current log level
     */
    protected string $level = LogLevel::DEBUG;

    /**
     * @var FormatterInterface Formatter for log messages
     */
    protected FormatterInterface $formatter;

    /**
     * @var array Log level priorities (higher = more severe)
     */
    protected array $levelPriorities = [
        LogLevel::DEBUG => 0,     // 'debug'
        LogLevel::INFO => 1,      // 'info'
        LogLevel::NOTICE => 2,    // 'notice'
        LogLevel::WARNING => 3,   // 'warning'
        LogLevel::ERROR => 4,     // 'error'
        LogLevel::CRITICAL => 5,  // 'critical'
        LogLevel::ALERT => 6,     // 'alert'
        LogLevel::EMERGENCY => 7  // 'emergency'
    ];

    /**
     * Constructor
     *
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(string $level = LogLevel::DEBUG, ?FormatterInterface $formatter = null)
    {
        $this->level = strtolower($level);
        $this->formatter = $formatter ?? new LineFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function setLevel(string $level): LoggerInterface
    {
        if (!isset($this->levelPriorities[$level])) {
            throw new \InvalidArgumentException("Invalid log level: $level");
        }

        $this->level = strtolower($level);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter): LoggerInterface
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    /**
     * Check if the level is allowed to be logged
     *
     * @param string $level
     * @return bool
     */
    protected function isLevelAllowed(string $level): bool
    {
        // Make sure level exists in the priorities array
        if (!isset($this->levelPriorities[$level])) {
            // You could add a fallback or trigger a warning
            error_log("Warning: Unknown log level '{$level}', defaulting to DEBUG");
            $level = LogLevel::DEBUG;
        }

        return $this->levelPriorities[$level] >= $this->levelPriorities[$this->level];
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if (!$this->isLevelAllowed($level)) {
            return;
        }

        // Check if message is already formatted (contains timestamp and level)
        $isAlreadyFormatted = false;
        if (is_string($message) &&
            preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[[A-Z]+\]/', $message)) {
            $isAlreadyFormatted = true;
        }

        if ($isAlreadyFormatted) {
            // If already formatted, write directly
            $this->write($level, $message, $context);
        } else {
            // If not formatted, format and then write
            $formattedMessage = $this->formatter->format($level, $message, $context);
            $this->write($level, $formattedMessage, $context);
        }
    }

    /**
     * Write a log message to the storage medium
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    abstract protected function write(string $level, string $message, array $context = []): void;
}