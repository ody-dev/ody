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
use Ody\Logger\Formatters\JsonFormatter;
use Ody\Logger\Formatters\LineFormatter;
use Psr\Log\LogLevel;

class FileLogger extends AbstractLogger
{
    /**
     * @var string Log file path
     */
    protected string $filePath;

    /**
     * @var bool Whether to rotate logs
     */
    protected bool $rotate = false;

    /**
     * @var int Maximum file size in bytes before rotation
     */
    protected int $maxFileSize = 10485760; // 10MB

    /**
     * Constructor
     *
     * @param string $filePath
     * @param string $level
     * @param FormatterInterface|null $formatter
     * @param bool $rotate
     * @param int $maxFileSize
     */
    public function __construct(
        string $filePath,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null,
        bool $rotate = false,
        int $maxFileSize = 10485760
    ) {
        parent::__construct($level, $formatter);

        $this->filePath = $filePath;
        $this->rotate = $rotate;
        $this->maxFileSize = $maxFileSize;

        // Ensure directory exists
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Create a file logger from configuration
     *
     * @param array $config
     * @return LoggerInterface
     * @throws \InvalidArgumentException
     */
    public static function create(array $config): LoggerInterface
    {
        // Make sure we have a path
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException("File logger requires a 'path' configuration value");
        }

        // Get the path and handle path placeholders
        $path = self::resolvePath($config['path']);

        // Add date suffix for daily files
        if (strpos($path, 'daily-') !== false) {
            $path = str_replace('daily-', 'daily-' . date('Y-m-d') . '-', $path);
        }

        // Create formatter
        $formatter = self::createFormatter($config);

        // Create and return the logger
        return new self(
            $path,
            $config['level'] ?? LogLevel::DEBUG,
            $formatter,
            $config['rotate'] ?? false,
            $config['max_file_size'] ?? 10485760
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
     * Resolve path with support for helper functions and environment variables
     *
     * @param string $path
     * @return string
     */
    protected static function resolvePath(string $path): string
    {
        // If the path starts with a function name, try to resolve it
        if (preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\(/', $path, $matches)) {
            $function = $matches[1];
            if (function_exists($function)) {
                // Extract the arguments
                preg_match('/^[^(]*\(([^)]*)\)/', $path, $argMatches);
                $argString = $argMatches[1] ?? '';

                // Parse the arguments
                $args = [];
                if (!empty($argString)) {
                    // Split by commas outside of quotes
                    $argParts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $argString);

                    // Process each argument
                    foreach ($argParts as $arg) {
                        $arg = trim($arg);
                        // Remove quotes
                        $arg = preg_replace('/^[\'"]|[\'"]$/', '', $arg);
                        $args[] = $arg;
                    }
                }

                // Call the function with the arguments
                $path = call_user_func_array($function, $args);
            }
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        if ($this->rotate && file_exists($this->filePath) && filesize($this->filePath) > $this->maxFileSize) {
            $this->rotateLogFile();
        }

        file_put_contents($this->filePath, $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Rotate log file
     *
     * @return void
     */
    protected function rotateLogFile(): void
    {
        $info = pathinfo($this->filePath);
        $rotatedFile = sprintf(
            '%s/%s-%s.%s',
            $info['dirname'],
            $info['filename'],
            date('Y-m-d-H-i-s'),
            $info['extension']
        );

        rename($this->filePath, $rotatedFile);
    }
}