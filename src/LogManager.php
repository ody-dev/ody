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
use Psr\Log\NullLogger;

/**
 * LogManager with enhanced driver discovery
 * Factory and manager for loggers with support for self-registering drivers
 */
class LogManager
{
    /**
     * @var array Default configuration
     */
    protected array $config = [
        'default' => 'file',
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => 'logs/app.log',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line',
                'rotate' => false,
                'max_file_size' => 10485760
            ],
            'stdout' => [
                'driver' => 'stream',
                'stream' => 'php://stdout',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line'
            ],
            'stderr' => [
                'driver' => 'stream',
                'stream' => 'php://stderr',
                'level' => LogLevel::ERROR,
                'formatter' => 'line'
            ],
            'daily' => [
                'driver' => 'file',
                'path' => 'logs/daily-',
                'level' => LogLevel::DEBUG,
                'formatter' => 'line',
                'rotate' => true,
                'max_file_size' => 5242880
            ],
        ]
    ];

    /**
     * @var LoggerInterface[] Array of created loggers
     */
    protected array $loggers = [];

    /**
     * @var array Custom driver to class mappings
     */
    protected array $driverMap = [];

    /**
     * @var bool Whether we're in debug mode
     */
    protected bool $debug = false;

    /**
     * @var bool Flag to prevent circular resolution of channels
     */
    protected array $resolvingChannels = [];

    /**
     * @var array Default namespaces to search for logger classes
     */
    protected array $namespaces = [
        '\\Ody\\Foundation\\Logging\\',
        '\\App\\Logging\\',
    ];

    /**
     * Constructor
     *
     * @param array $config Optional configuration to override defaults
     */
    public function __construct(array $config = [])
    {
        // Merge custom config with defaults
        $this->config = array_replace_recursive($this->config, $config);

        // Set debug mode
        $this->debug = (bool)env('APP_DEBUG', false);

        // Initialize default driver map for built-in loggers
        $this->initDefaultDriverMap();
    }

    /**
     * Initialize default driver map for built-in loggers
     *
     * @return void
     */
    protected function initDefaultDriverMap(): void
    {
        $this->driverMap = [
            'file' => FileLogger::class,
            'stream' => StreamLogger::class,
            'null' => NullLogger::class,
            'group' => GroupLogger::class,
            'callable' => CallableLogger::class
        ];
    }

    /**
     * Get a logger instance
     *
     * @param string|null $channel Channel name or null for default
     * @return LoggerInterface
     */
    public function channel(?string $channel = null): LoggerInterface
    {
        // Use default channel if none specified
        $channel = $channel ?? $this->config['default'];

        // If channel doesn't exist in config, fail gracefully
        if (!isset($this->config['channels'][$channel])) {
            // Log the error if in debug mode
            if ($this->debug) {
                error_log("Log channel '{$channel}' is not defined. Using default channel.");
            }

            // Fallback to default channel
            $channel = $this->config['default'];

            // If default channel also doesn't exist, use emergency fallback
            if (!isset($this->config['channels'][$channel])) {
                if ($this->debug) {
                    error_log("Default log channel '{$channel}' is not defined. Using NullLogger.");
                }
                return new NullLogger();
            }
        }

        // Return cached instance if available
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        // Detect circular dependencies
        if (isset($this->resolvingChannels[$channel])) {
            error_log("Circular dependency detected for log channel '{$channel}'. Using NullLogger to break the cycle.");
            return new NullLogger();
        }

        // Mark this channel as being resolved to detect circular dependencies
        $this->resolvingChannels[$channel] = true;

        // Create new logger instance
        try {
            $this->loggers[$channel] = $this->createLogger($channel);
        } catch (\Throwable $e) {
            // Log the error
            error_log("Failed to create logger for channel '{$channel}': " . $e->getMessage());

            // Fallback to NullLogger to avoid disrupting application
            $this->loggers[$channel] = new NullLogger();
        } finally {
            // Done resolving this channel
            unset($this->resolvingChannels[$channel]);
        }

        return $this->loggers[$channel];
    }

    /**
     * Create a new logger instance based on config
     *
     * @param string $channel
     * @return LoggerInterface
     */
    protected function createLogger(string $channel): LoggerInterface
    {
        $config = $this->config['channels'][$channel];

        if (!isset($config['driver'])) {
            throw new \InvalidArgumentException("Log channel '{$channel}' has no driver specified");
        }

        $driver = $config['driver'];

        // Special case for group/stack driver since it needs access to the LogManager
        if ($driver === 'group' || $driver === 'stack') {
            return $this->createGroupLogger($config);
        }

        // 1. If explicit class is defined in config, use it directly
        if (isset($config['class'])) {
            return $this->createLoggerFromClass($config['class'], $config);
        }

        // 2. Check the driver map for registered loggers
        if (isset($this->driverMap[$driver])) {
            return $this->createLoggerFromClass($this->driverMap[$driver], $config);
        }

        // 3. Try to auto-discover the logger class by convention
        $loggerClass = $this->discoverLoggerClass($driver);
        if ($loggerClass) {
            return $this->createLoggerFromClass($loggerClass, $config);
        }

        // 4. Cannot find an appropriate logger
        throw new \InvalidArgumentException("Log driver '{$driver}' is not supported and no matching class was found");
    }

    /**
     * Attempt to discover a logger class by convention
     *
     * @param string $driver
     * @return string|null The fully qualified class name if found, null otherwise
     */
    protected function discoverLoggerClass(string $driver): ?string
    {
        $className = ucfirst($driver) . 'Logger';

        foreach ($this->namespaces as $namespace) {
            $fullyQualifiedClass = $namespace . $className;
            if (class_exists($fullyQualifiedClass) &&
                method_exists($fullyQualifiedClass, 'create')) {
                return $fullyQualifiedClass;
            }
        }

        return null;
    }

    /**
     * Create a logger from a class name
     *
     * @param string $class
     * @param array $config
     * @return LoggerInterface
     * @throws \InvalidArgumentException If the class doesn't exist or doesn't have a create method
     */
    protected function createLoggerFromClass(string $class, array $config): LoggerInterface
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Logger class '{$class}' does not exist");
        }

        if (!method_exists($class, 'create')) {
            throw new \InvalidArgumentException("Logger class '{$class}' must have a static 'create' method");
        }

        return $class::create($config);
    }

    /**
     * Create a group logger
     *
     * @param array $config
     * @return GroupLogger
     */
    protected function createGroupLogger(array $config): GroupLogger
    {
        // Ensure channels array exists
        if (!isset($config['channels']) || !is_array($config['channels']) || empty($config['channels'])) {
            throw new \InvalidArgumentException("Group logger requires a 'channels' configuration array");
        }

        // Create individual loggers for each channel
        $loggers = [];
        $errors = [];

        foreach ($config['channels'] as $channelName) {
            // Skip if the channel would cause a circular reference
            if (isset($this->resolvingChannels[$channelName])) {
                $errors[] = "Skipped circular reference to channel '{$channelName}'";
                continue;
            }

            try {
                // Check if channel exists in config
                if (!isset($this->config['channels'][$channelName])) {
                    $errors[] = "Channel '{$channelName}' not found in configuration";
                    continue;
                }

                // Use channel() which creates and caches loggers
                $loggers[] = $this->channel($channelName);
            } catch (\Throwable $e) {
                $errors[] = "Error creating logger for channel '{$channelName}': " . $e->getMessage();
            }
        }

        // If we had errors, log them
        if (!empty($errors)) {
            foreach ($errors as $error) {
                error_log("[LogManager] GroupLogger error: " . $error);
            }
        }

        // We need to create a formatter for the group logger
        $formatter = $this->createFormatter($config);

        // Create the group logger with the collected channel loggers
        return new GroupLogger(
            $loggers,
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }

    /**
     * Create a formatter instance based on config
     *
     * @param array $config
     * @return FormatterInterface
     */
    protected function createFormatter(array $config): FormatterInterface
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
     * Register a custom driver mapping
     *
     * @param string $driver The driver name
     * @param string $class Fully qualified class name
     * @return self
     */
    public function registerDriver(string $driver, string $class): self
    {
        $this->driverMap[$driver] = $class;

        // If this driver was already resolved and cached, clear it
        // so it will be recreated with the new implementation
        foreach ($this->loggers as $channel => $logger) {
            if (isset($this->config['channels'][$channel]['driver']) &&
                $this->config['channels'][$channel]['driver'] === $driver) {
                unset($this->loggers[$channel]);
            }
        }

        return $this;
    }

    /**
     * Register a namespace for auto-discovery
     *
     * @param string $namespace
     * @return self
     */
    public function registerNamespace(string $namespace): self
    {
        // Ensure namespace ends with \\
        $namespace = rtrim($namespace, '\\') . '\\';

        // Only add if not already registered
        if (!in_array($namespace, $this->namespaces)) {
            $this->namespaces[] = $namespace;
        }

        return $this;
    }

    /**
     * Get available channel names
     *
     * @return array
     */
    public function getChannels(): array
    {
        return array_keys($this->config['channels']);
    }

    /**
     * Check if a channel exists
     *
     * @param string $channel
     * @return bool
     */
    public function hasChannel(string $channel): bool
    {
        return isset($this->config['channels'][$channel]);
    }

    /**
     * Add a new channel configuration
     *
     * @param string $channel
     * @param array $config
     * @return self
     */
    public function addChannel(string $channel, array $config): self
    {
        $this->config['channels'][$channel] = $config;

        // If this channel was already resolved, clear it so it will be recreated next time
        if (isset($this->loggers[$channel])) {
            unset($this->loggers[$channel]);
        }

        return $this;
    }

    /**
     * Get registered driver mappings
     *
     * @return array
     */
    public function getDriverMap(): array
    {
        return $this->driverMap;
    }

    /**
     * Get registered namespaces
     *
     * @return array
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }
}