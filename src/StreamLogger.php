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

/**
 * Stream Logger
 * Logs messages to a stream (stdout, stderr, etc.) with self-registration capabilities
 */
class StreamLogger extends AbstractLogger
{
    /**
     * @var resource Stream resource
     */
    protected $stream;

    /**
     * @var bool Whether to close the stream on destruct
     */
    protected bool $closeOnDestruct = false;

    /**
     * Constructor
     *
     * @param mixed $stream Stream resource or string (e.g., 'php://stdout')
     * @param string $level
     * @param FormatterInterface|null $formatter
     * @param bool $closeOnDestruct
     */
    public function __construct(
        $stream,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        bool $closeOnDestruct = false
    ) {
        parent::__construct($level, $formatter);

        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->stream = fopen($stream, 'a');
            $this->closeOnDestruct = true;
        } else {
            throw new \InvalidArgumentException('Stream must be a resource or a string');
        }

        if (!is_resource($this->stream)) {
            throw new \RuntimeException('Failed to open stream');
        }

        $this->closeOnDestruct = $closeOnDestruct;
    }

    /**
     * Create a stream logger from configuration
     *
     * @param array $config
     * @return LoggerInterface
     * @throws \InvalidArgumentException
     */
    public static function create(array $config): LoggerInterface
    {
        if (!isset($config['stream'])) {
            throw new \InvalidArgumentException("Stream logger requires a 'stream' configuration value");
        }

        // Create formatter
        $formatter = self::createFormatter($config);

        // Create and return the logger
        return new self(
            $config['stream'],
            $config['level'] ?? LogLevel::DEBUG,
            $formatter,
            $config['close_on_destruct'] ?? false
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
     * Destructor
     */
    public function __destruct()
    {
        if ($this->closeOnDestruct && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        fwrite($this->stream, $message . PHP_EOL);
    }
}