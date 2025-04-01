<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;

use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface as MonologFormatterInterface;
use Monolog\Handler\HandlerInterface as MonologHandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level as MonologLevel;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Throwable;

class LogManager
{
    /**
     * @var array Default configuration structure (examples using Monolog)
     */
    protected array $config = [];

    /**
     * @var LoggerInterface[] Array of created logger instances (keyed by channel name)
     */
    protected array $loggers = [];

    /**
     * @var bool Whether we're in debug mode (affects error reporting)
     */
    protected bool $debug = false;

    /**
     * @var array Tracks channels currently being resolved to prevent circular dependencies
     */
    protected array $resolvingChannels = [];


    /**
     * Constructor
     *
     * @param array $config Optional configuration to override defaults
     */
    public function __construct(array $config = [])
    {
        // Merge provided config with defaults
        $this->config = array_replace_recursive($this->config, $config);

        // Determine debug mode from config or environment variable
        $this->debug = (bool)($this->config['debug'] ?? env('APP_DEBUG', false));
    }

    /**
     * Get a logger instance for the specified channel.
     *
     * @param string|null $channel Channel name or null for the default channel.
     * @return LoggerInterface The resolved logger instance (or NullLogger on failure).
     */
    public function channel(?string $channel = null): LoggerInterface
    {
        // Determine the channel name (use default if null)
        $channel = $channel ?? $this->config['default'] ?? 'stack'; // Sensible default

        // Check if the channel configuration exists
        if (!isset($this->config['channels'][$channel])) {
            if ($this->debug) { // Only log error in debug mode
                error_log("[LogManager] Log channel '{$channel}' is not defined. Using NullLogger.");
            }
            return new NullLogger(); // Fallback gracefully
        }

        // Return cached instance if already created
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        // Prevent circular dependencies during resolution
        if (isset($this->resolvingChannels[$channel])) {
            error_log("[LogManager] Circular dependency detected for log channel '{$channel}'. Using NullLogger.");
            return new NullLogger();
        }

        // Mark channel as being resolved
        $this->resolvingChannels[$channel] = true;

        try {
            // Create the logger instance
            $logger = $this->createLogger($channel);
            // Cache the created logger
            $this->loggers[$channel] = $logger;
        } catch (Throwable $e) {
            // Log the creation failure and provide a NullLogger fallback
            error_log("[LogManager] Failed to create logger for channel '{$channel}': " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->loggers[$channel] = new NullLogger();
        } finally {
            // Ensure the resolving flag is removed regardless of success/failure
            unset($this->resolvingChannels[$channel]);
        }

        return $this->loggers[$channel];
    }

    /**
     * Create a logger instance based on its configuration.
     *
     * @param string $channel The name of the channel to create.
     * @return LoggerInterface
     * @throws InvalidArgumentException If the driver is missing or unsupported.
     */
    protected function createLogger(string $channel): LoggerInterface
    {
        // Get the configuration for this specific channel
        $config = $this->config['channels'][$channel];
        // Determine the driver ('monolog', 'stack', 'null')
        $driver = $config['driver'] ?? null;

        if (!$driver) {
            throw new InvalidArgumentException("Log channel '{$channel}' has no driver specified.");
        }

        // Route to the appropriate creator method
        switch ($driver) {
            case 'monolog':
                return $this->createMonologLogger($channel, $config);
            case 'stack':
                return $this->createStackLogger($channel, $config);
            case 'null':
                return new NullLogger();
            default:
                // Only Monolog, stack, and null drivers are now supported
                throw new InvalidArgumentException("Unsupported log driver '{$driver}' specified for channel '{$channel}'. Only 'monolog', 'stack', or 'null' are supported.");
        }
    }

    /**
     * Create a Monolog logger instance with configured handler, formatter, and processors.
     *
     * @param string $channel The channel name (used as Monolog logger name).
     * @param array $config The configuration for this channel.
     * @return MonologLogger
     * @throws InvalidArgumentException If handler or formatter classes are invalid.
     * @throws Throwable If handler instantiation fails.
     */
    protected function createMonologLogger(string $channel, array $config): MonologLogger
    {
        // Create the base Monolog logger instance
        $logger = new MonologLogger($channel);

        $handlerClass = $config['handler'] ?? StreamHandler::class; // Sensible default
        if (!class_exists($handlerClass) || !is_subclass_of($handlerClass, MonologHandlerInterface::class)) {
            throw new InvalidArgumentException("Invalid Monolog handler class specified for channel '{$channel}': {$handlerClass}");
        }

        $handlerArgsConfig = $config['with'] ?? [];
        $preparedArgs = $this->prepareMonologConstructorArgs($handlerArgsConfig, $channel);

        /** @var MonologHandlerInterface $handler */
        try {
            if ($handlerClass === StreamHandler::class) {
                if (!isset($preparedArgs['stream'])) {
                    throw new InvalidArgumentException("Missing 'stream' configuration in 'with' key for StreamHandler channel '{$channel}'.");
                }

                $stream = $preparedArgs['stream'];
                $level = $preparedArgs['level'] ?? MonologLevel::Debug; // Initial level, overridden below
                $bubble = $preparedArgs['bubble'] ?? true;
                $filePermission = $preparedArgs['filePermission'] ?? null;
                $useLocking = $preparedArgs['useLocking'] ?? false;
                $handler = new StreamHandler($stream, $level, $bubble, $filePermission, $useLocking);
            } else {
                // Fallback for other handlers using prepared arguments and order
                $handler = new $handlerClass(...array_values($preparedArgs));
            }
        } catch (Throwable $e) {
            // Catch ArgumentCountError or other instantiation issues
            error_log("[LogManager] Failed to instantiate handler '{$handlerClass}' for channel '{$channel}'. Check 'with' config. Error: {$e->getMessage()}");
            throw $e; // Re-throw critical error
        }

        $handlerLevelConfig = $config['level'] ?? LogLevel::DEBUG; // Default to DEBUG
        try {
            $monologLevel = $this->psrToMonologLevel($handlerLevelConfig);
            if (method_exists($handler, 'setLevel')) {
                $handler->setLevel($monologLevel);
            } elseif (!isset($preparedArgs['level'])) { // Check if level was already set via constructor
                error_log("[LogManager] Handler '{$handlerClass}' for channel '{$channel}' might not support setLevel() and level was not passed in 'with'. Using handler's default level.");
            }
        } catch (InvalidArgumentException $e) {
            error_log("[LogManager] Invalid log level '{$handlerLevelConfig}' specified for channel '{$channel}'. Defaulting handler to DEBUG. Error: {$e->getMessage()}");
            if (method_exists($handler, 'setLevel')) {
                $handler->setLevel(MonologLevel::Debug); // Safe fallback
            }
        }

        // --- 2. Create and Set Formatter ---
        if (isset($config['formatter'])) {
            $formatterClass = $config['formatter'];
            if (!class_exists($formatterClass) || !is_subclass_of($formatterClass, MonologFormatterInterface::class)) {
                throw new InvalidArgumentException("Invalid Monolog formatter class specified for channel '{$channel}': {$formatterClass}");
            }
            // Prepare arguments for the formatter's constructor from 'formatter_with'
            $formatterArgs = $this->prepareMonologConstructorArgs($config['formatter_with'] ?? [], $channel);
            /** @var MonologFormatterInterface $formatter */
            $formatter = new $formatterClass(...array_values($formatterArgs));
            $handler->setFormatter($formatter);
        }

        $logger->pushHandler($handler);

        if (!empty($config['processors']) && is_array($config['processors'])) {
            foreach ($config['processors'] as $processorEntry) {
                $processor = $this->resolveProcessor($processorEntry, $channel);
                if ($processor) {
                    $logger->pushProcessor($processor);
                }
            }
        }

        return $logger;
    }

    /**
     * Resolves a processor from configuration entry.
     *
     * @param mixed $processorEntry Class name, callable, or object.
     * @param string $channel Channel name for context.
     * @return callable|ProcessorInterface|null Resolved processor or null if invalid.
     */
    protected function resolveProcessor(mixed $processorEntry, string $channel): callable|ProcessorInterface|null
    {
        // Handle class names
        if (is_string($processorEntry) && class_exists($processorEntry) && is_subclass_of($processorEntry, ProcessorInterface::class)) {
            try {
                // Simple instantiation first
                return new $processorEntry();
            } catch (Throwable $instantiationError) {
                error_log("[LogManager] Failed to directly instantiate processor '{$processorEntry}' for channel '{$channel}'. Error: {$instantiationError->getMessage()}");
                // Optionally, add container resolution here if needed
                return null;
            }
        } // Handle callables
        elseif (is_callable($processorEntry)) {
            return $processorEntry;
        } // Handle already instantiated objects
        elseif ($processorEntry instanceof ProcessorInterface) {
            return $processorEntry;
        } // Log invalid entries
        else {
            error_log("[LogManager] Invalid processor specified for channel '{$channel}': " . print_r($processorEntry, true));
            return null;
        }
    }


    /**
     * Prepares arguments for Monolog component constructors, mapping PSR levels and resolving paths.
     *
     * @param array $config The 'with' or 'formatter_with' config array.
     * @param string $channel The channel name (for context in errors).
     * @return array Prepared arguments.
     */
    protected function prepareMonologConstructorArgs(array $config, string $channel): array
    {
        $args = $config; // Start with the config values

        // Map PSR LogLevel string to Monolog Level enum/int if 'level' key exists
        if (isset($args['level'])) {
            try {
                $args['level'] = $this->psrToMonologLevel($args['level']);
            } catch (InvalidArgumentException $e) {
                error_log("[LogManager] Invalid log level '{$args['level']}' specified in 'with' config for channel '{$channel}'. It will be ignored for constructor. Error: {$e->getMessage()}");
                unset($args['level']); // Remove invalid level
            }
        }

        // Resolve file paths for 'stream' argument if it's not a special PHP stream
        if (isset($args['stream']) && is_string($args['stream']) && !str_starts_with($args['stream'], 'php://')) {
            $args['stream'] = $this->resolveStreamPath($args['stream']);
        }

        // Map common boolean string values from env() to actual booleans
        foreach (['bubble', 'useLocking'] as $key) {
            if (isset($args[$key]) && is_string($args[$key])) {
                $args[$key] = filter_var($args[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $args[$key];
            }
        }

        return $args;
    }

    /**
     * Resolves a stream path, prepending storage path if necessary.
     *
     * @param string $path The stream path from config.
     * @return string The resolved, absolute path.
     */
    protected function resolveStreamPath(string $path): string
    {
        // Use storage_path helper if available and path is not absolute
        if (function_exists('storage_path') && $path[0] !== '/' && !(strlen($path) > 1 && $path[1] === ':')) {
            return storage_path(ltrim($path, '/'));
        } // Fallback path construction if storage_path doesn't exist or path is absolute
        elseif ($path[0] !== '/' && !(strlen($path) > 1 && $path[1] === ':')) {
            $base = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 4); // Adjust depth as needed
            $resolvedPath = $base . '/storage/' . ltrim($path, '/'); // Assume storage dir
            // Ensure the directory exists
            $logDir = dirname($resolvedPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            return $resolvedPath;
        }
        // Path is already absolute
        return $path;
    }


    /**
     * Converts PSR-3 LogLevel strings to Monolog Level enum/int.
     *
     * @param string|int $level PSR-3 LogLevel string (or potentially already an int/enum).
     * @return MonologLevel Monolog Level enum.
     * @throws InvalidArgumentException If the level string is invalid.
     */
    protected function psrToMonologLevel($level): MonologLevel
    {
        if ($level instanceof MonologLevel) {
            return $level; // Already a Monolog level
        }
        if (is_int($level)) {
            // Attempt to convert valid Monolog integer level to Enum
            try {
                return MonologLevel::from($level);
            } catch (\ValueError $e) {
                throw new InvalidArgumentException("Invalid Monolog integer level provided: {$level}");
            }
        }

        $levelStr = strtolower((string)$level);

        // Map PSR-3 strings to Monolog Level enums
        $map = [
            LogLevel::DEBUG => MonologLevel::Debug,
            LogLevel::INFO => MonologLevel::Info,
            LogLevel::NOTICE => MonologLevel::Notice,
            LogLevel::WARNING => MonologLevel::Warning,
            LogLevel::ERROR => MonologLevel::Error,
            LogLevel::CRITICAL => MonologLevel::Critical,
            LogLevel::ALERT => MonologLevel::Alert,
            LogLevel::EMERGENCY => MonologLevel::Emergency,
        ];

        if (!isset($map[$levelStr])) {
            throw new InvalidArgumentException("Invalid PSR-3 log level string provided: {$level}");
        }

        return $map[$levelStr];
    }


    /**
     * Create a stack/group logger (which itself is a Monolog logger).
     *
     * @param string $stackChannelName The name for the stack channel itself.
     * @param array $config The configuration for the stack channel.
     * @return LoggerInterface Returns MonologLogger if possible, otherwise NullLogger.
     */
    protected function createStackLogger(string $stackChannelName, array $config): LoggerInterface
    {
        $channels = $config['channels'] ?? [];
        if (empty($channels) || !is_array($channels)) {
            throw new InvalidArgumentException("Stack logger '{$stackChannelName}' requires a non-empty 'channels' array configuration.");
        }

        $handlers = [];
        $processors = []; // Collect unique processors

        foreach ($channels as $channelName) {
            // Prevent infinite recursion if a stack includes itself or is currently being resolved
            if ($channelName === $stackChannelName || isset($this->resolvingChannels[$channelName])) {
                error_log("[LogManager] Skipping circular reference in stack logger '{$stackChannelName}' for channel '{$channelName}'.");
                continue;
            }
            try {
                // Resolve the logger for the sub-channel
                $loggerInstance = $this->channel($channelName);

                // If the sub-logger is a Monolog instance, aggregate its handlers and processors
                if ($loggerInstance instanceof MonologLogger) {
                    $handlers = array_merge($handlers, $loggerInstance->getHandlers());
                    $processors = array_merge($processors, $loggerInstance->getProcessors());
                } // Log a warning if a non-Monolog, non-Null logger is included
                else if (!($loggerInstance instanceof NullLogger)) {
                    error_log("[LogManager] Stack channel '{$channelName}' in stack '{$stackChannelName}' is not a Monolog logger. Its logs might not be processed by the stack handlers/processors.");
                }

            } catch (Throwable $e) {
                error_log("[LogManager] Error resolving channel '{$channelName}' for stack logger '{$stackChannelName}': " . $e->getMessage());
                // Decide whether to ignore errors based on config
                if (!($config['ignore_exceptions'] ?? false)) {
                    throw $e; // Rethrow if not ignoring exceptions
                }
            }
        }

        // If no handlers were collected
        if (empty($handlers)) {
            error_log("[LogManager] Stack logger '{$stackChannelName}' created with no valid Monolog handlers.");
            return new NullLogger(); // Fallback
        }

        // Create a new Monolog logger for the stack channel
        $logger = new MonologLogger($stackChannelName);
        // Set the unique handlers collected from sub-channels
        $logger->setHandlers(array_values(array_unique($handlers, SORT_REGULAR)));

        // Add unique processors collected from sub-channels
        $uniqueProcessors = array_values(array_unique($processors, SORT_REGULAR));
        foreach ($uniqueProcessors as $processor) {
            if (is_callable($processor) || $processor instanceof ProcessorInterface) {
                $logger->pushProcessor($processor);
            }
        }

        return $logger;
    }

    // --- Removed Methods related to custom drivers ---
    // - initDefaultDriverMap (now empty or minimal)
    // - createCustomLogger
    // - discoverCustomLoggerClass
    // - createLoggerFromClassExpectingCreate
    // - createOdyFormatter
    // - registerDriver
    // - registerNamespace
    // - getDriverMap
    // - getNamespaces

    // --- Methods for managing channels (kept) ---

    /**
     * Get the names of all configured channels.
     *
     * @return array<string>
     */
    public function getChannels(): array
    {
        return array_keys($this->config['channels']);
    }

    /**
     * Check if a channel configuration exists.
     *
     * @param string $channel The name of the channel.
     * @return bool True if the channel is configured, false otherwise.
     */
    public function hasChannel(string $channel): bool
    {
        return isset($this->config['channels'][$channel]);
    }

    /**
     * Add or overwrite a channel configuration dynamically at runtime.
     * If the channel was already resolved, its cached instance will be cleared.
     *
     * @param string $channel The name of the channel to add or modify.
     * @param array $config The configuration array for the channel. Must include 'driver' key ('monolog', 'stack', 'null').
     * @return self
     */
    public function addChannel(string $channel, array $config): self
    {
        if (!isset($config['driver']) || !in_array($config['driver'], ['monolog', 'stack', 'null'])) {
            error_log("[LogManager] Cannot add channel '{$channel}': Invalid or missing 'driver'. Only 'monolog', 'stack', 'null' supported.");
            return $this;
        }
        // Store the new configuration
        $this->config['channels'][$channel] = $config;
        // Clear any cached instance for this channel
        unset($this->loggers[$channel]);
        return $this;
    }

    /**
     * Dynamically pass methods to the default logger instance.
     * Allows calling `$logManager->info('message')` which forwards to
     * `$logManager->channel()->info('message')`.
     *
     * @param string $method The logging method (e.g., 'info', 'error', 'debug').
     * @param array $parameters The arguments passed to the method.
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        // Resolve the default logger channel and call the method on it
        return $this->channel()->{$method}(...$parameters);
    }
}
