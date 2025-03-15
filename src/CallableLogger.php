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
 * Callable Logger
 * Logs messages using a callable handler (useful for custom log handling)
 */
class CallableLogger extends AbstractLogger
{
    /**
     * @var callable Handler function
     */
    protected $handler;

    /**
     * Constructor
     *
     * @param callable $handler
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        callable $handler,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);

        $this->handler = $handler;
    }

    /**
     * Create a callable logger from configuration
     *
     * @param array $config
     * @return LoggerInterface
     * @throws \InvalidArgumentException
     */
    public static function create(array $config): LoggerInterface
    {
        if (!isset($config['handler']) || !is_callable($config['handler'])) {
            throw new \InvalidArgumentException("Callable logger requires a 'handler' configuration value");
        }

        // Create formatter if specified
        $formatter = null;
        if (isset($config['formatter'])) {
            $formatter = self::createFormatter($config);
        }

        return new self(
            $config['handler'],
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }

    /**
     * Create a formatter based on configuration
     *
     * @param array $config
     * @return FormatterInterface
     */
    protected static function createFormatter(array $config): FormatterInterface
    {
        $formatterType = $config['formatter'] ?? 'line';

        switch ($formatterType) {
            case 'json':
                return new JsonFormatter();

            case 'line':
            default:
                return new LineFormatter(
                    $config['format'] ?? null,
                    $config['date_format'] ?? null
                );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        call_user_func($this->handler, $level, $message, $context);
    }
}