# ODY Logger

A flexible, PSR-3 compliant logging system for the ODY Framework with support for multiple output channels, formatters, and Swoole coroutines.

## Installation

```bash
composer require ody/logger
```

## Basic Usage

```php
use Ody\Logger\FileLogger;
use Psr\Log\LogLevel;

// Create a simple file logger
$logger = new FileLogger('/path/to/app.log', LogLevel::DEBUG);

// Log messages at different levels
$logger->debug('Detailed debug information');
$logger->info('Interesting event');
$logger->notice('Normal but significant event');
$logger->warning('Exceptional occurrence that is not an error');
$logger->error('Runtime error that does not require immediate action');
$logger->critical('Critical conditions', ['user_id' => 42]);
$logger->alert('Action must be taken immediately');
$logger->emergency('System is unusable');
```

## Using the Log Manager

The LogManager provides a convenient way to manage multiple logging channels:

```php
use Ody\Logger\LogManager;

// Create a manager with default configuration
$logManager = new LogManager();

// Or with custom configuration
$logManager = new LogManager([
    'default' => 'daily',
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'debug',
            'formatter' => 'line'
        ],
        'daily' => [
            'driver' => 'file',
            'path' => 'logs/daily-',
            'level' => 'info',
            'formatter' => 'line',
            'rotate' => true,
            'max_file_size' => 5242880 // 5MB
        ],
        'error' => [
            'driver' => 'file',
            'path' => 'logs/error.log',
            'level' => 'error'
        ],
        'stdout' => [
            'driver' => 'stream',
            'stream' => 'php://stdout',
            'level' => 'debug'
        ]
    ]
]);

// Get a logger by channel name
$logger = $logManager->channel('daily');
$logger->info('This is logged to the daily channel');

// Use default channel
$defaultLogger = $logManager->channel();
$defaultLogger->error('This is logged to the default channel');
```

## Swoole Support

The ODY Logger provides enhanced support for Swoole environments, including non-blocking I/O operations through coroutines:

```php
use Ody\Logger\SwooleLogManager;

// Create a Swoole-aware log manager
$logManager = new SwooleLogManager([
    'default' => 'file',
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'debug'
        ],
        'swoole_table' => [
            'driver' => 'swoole_table',
            'max_entries' => 5000,
            'level' => 'debug'
        ]
    ]
]);

$logger = $logManager->channel('swoole_table');
$logger->info('This is stored in a Swoole Table for high-performance in-memory logging');

// Later, you can flush the in-memory logs to a persistent storage
$fileLogger = $logManager->channel('file');
$tableLogger = $logManager->channel('swoole_table');
$tableLogger->flush($fileLogger, true); // The second parameter determines if the table should be cleared after flushing
```

## Available Logger Types

### File Logger

Logs messages to a file with optional rotation:

```php
use Ody\Logger\FileLogger;
use Psr\Log\LogLevel;

$logger = new FileLogger(
    '/path/to/app.log',   // File path
    LogLevel::DEBUG,      // Minimum log level
    null,                 // Optional formatter
    true,                 // Enable log rotation
    10485760              // Max file size before rotation (10MB)
);
```

### Stream Logger

Logs messages to any PHP stream (stdout, stderr, etc.):

```php
use Ody\Logger\StreamLogger;
use Psr\Log\LogLevel;

$logger = new StreamLogger(
    'php://stdout',       // Stream (can be a resource or a string)
    LogLevel::DEBUG,      // Minimum log level
    null,                 // Optional formatter
    false                 // Close stream on destruct
);
```

### Swoole Table Logger

Stores logs in a Swoole Table for high-performance in-memory logging:

```php
use Ody\Logger\SwooleTableLogger;
use Psr\Log\LogLevel;

$logger = new SwooleTableLogger(
    10000,                // Maximum number of log entries
    LogLevel::DEBUG,      // Minimum log level
    null                  // Optional formatter
);

// Later, flush logs to a persistent storage
$fileLogger = new FileLogger('/path/to/app.log');
$logger->flush($fileLogger, true);
```

### Group Logger

Sends log messages to multiple loggers:

```php
use Ody\Logger\GroupLogger;
use Ody\Logger\FileLogger;
use Ody\Logger\StreamLogger;
use Psr\Log\LogLevel;

$fileLogger = new FileLogger('/path/to/app.log');
$streamLogger = new StreamLogger('php://stdout');

$logger = new GroupLogger(
    [$fileLogger, $streamLogger], // Array of loggers
    LogLevel::DEBUG,              // Minimum log level
    null                          // Optional formatter
);
```

### Callable Logger

Logs messages using a custom callable handler:

```php
use Ody\Logger\CallableLogger;
use Psr\Log\LogLevel;

$handler = function(string $level, string $message, array $context = []) {
    // Custom logging logic
    echo "[$level] $message" . PHP_EOL;
};

$logger = new CallableLogger(
    $handler,           // Callable handler
    LogLevel::DEBUG,    // Minimum log level
    null                // Optional formatter
);
```

### Null Logger

A logger that discards all messages (useful for testing):

```php
use Ody\Logger\NullLogger;
use Psr\Log\LogLevel;

$logger = new NullLogger(
    LogLevel::DEBUG,    // Minimum log level (doesn't matter since nothing is logged)
    null                // Optional formatter
);
```

## Formatters

ODY Logger includes multiple formatters to structure your log messages:

### Line Formatter

Formats logs as text lines with customizable format:

```php
use Ody\Logger\LineFormatter;

$formatter = new LineFormatter(
    "[%datetime%] [%level%] %message% %context%", // Format string
    "Y-m-d H:i:s"                                // DateTime format
);
```

### JSON Formatter

Formats logs as JSON objects for easier parsing:

```php
use Ody\Logger\JsonFormatter;

$formatter = new JsonFormatter();
```

## Creating Custom Loggers

You can easily create custom loggers by extending the AbstractLogger class:

```php
use Ody\Logger\AbstractLogger;
use Psr\Log\LogLevel;

class CustomLogger extends AbstractLogger
{
    public static function create(array $config): LoggerInterface
    {
        // Create formatter based on config
        $formatter = self::createFormatter($config);
        
        // Return new instance
        return new self(
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }
    
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
    
    protected function write(string $level, string $message, array $context = []): void
    {
        // Implement custom logging logic here
    }
}
```

## API Reference

### Interfaces

#### `LoggerInterface`

Extends PSR-3's `LoggerInterface` with additional methods:

- `setLevel(string $level): self` - Set the minimum log level
- `getLevel(): string` - Get the current log level
- `setFormatter(FormatterInterface $formatter): self` - Set the formatter
- `getFormatter(): FormatterInterface` - Get the current formatter

#### `FormatterInterface`

Defines how log messages should be formatted:

- `format(string $level, string $message, array $context = []): string` - Format a log message

### Abstract Classes

#### `AbstractLogger`

Base implementation of the `LoggerInterface` that provides common functionality:

- `__construct(string $level, ?FormatterInterface $formatter)` - Constructor
- `setLevel(string $level): LoggerInterface` - Set minimum log level
- `getLevel(): string` - Get current log level
- `setFormatter(FormatterInterface $formatter): LoggerInterface` - Set formatter
- `getFormatter(): FormatterInterface` - Get current formatter
- `log($level, $message, array $context = []): void` - Log a message
- `protected isLevelAllowed(string $level): bool` - Check if a level can be logged
- `abstract protected write(string $level, string $message, array $context = []): void` - Write a log message (must be implemented by child classes)

### Logger Classes

#### `FileLogger`

Logs messages to a file:

- `__construct(string $filePath, string $level, ?FormatterInterface $formatter, bool $rotate, int $maxFileSize)` - Constructor
- `static create(array $config): LoggerInterface` - Create from configuration
- `protected write(string $level, string $message, array $context = []): void` - Write to file
- `protected rotateLogFile(): void` - Rotate log file when size limit is reached
- `protected static resolvePath(string $path): string` - Resolve path with support for functions and variables

#### `StreamLogger`

Logs messages to a stream:

- `__construct($stream, string $level, ?FormatterInterface $formatter, bool $closeOnDestruct)` - Constructor
- `static create(array $config): LoggerInterface` - Create from configuration
- `protected write(string $level, string $message, array $context = []): void` - Write to stream

#### `SwooleTableLogger`

Logs messages to a Swoole Table:

- `__construct(int $maxEntries, string $level, ?FormatterInterface $formatter)` - Constructor
- `protected initializeTable(): void` - Initialize Swoole Table
- `protected write(string $level, string $message, array $context = []): void` - Write to Swoole Table
- `getAll(): array` - Get all log entries
- `flush(LoggerInterface $destination, bool $clear = false): void` - Flush logs to another logger
- `clear(): void` - Clear all log entries

#### `SwooleFileLogger`

File logger with Swoole coroutine support:

- `protected write(string $level, string $message, array $context = []): void` - Write to file with coroutine support

#### `GroupLogger`

Logs messages to multiple loggers:

- `__construct(array $loggers, string $level, ?FormatterInterface $formatter)` - Constructor
- `static create(array $config): LoggerInterface` - Create from configuration
- `addLogger(LoggerInterface $logger): self` - Add a logger to the group
- `protected write(string $level, string $message, array $context = []): void` - Write to all loggers
- `getLoggers(): array` - Get all loggers in this group
- `count(): int` - Count the number of loggers

#### `CallableLogger`

Logs messages using a custom callable:

- `__construct(callable $handler, string $level, ?FormatterInterface $formatter)` - Constructor
- `static create(array $config): LoggerInterface` - Create from configuration
- `protected write(string $level, string $message, array $context = []): void` - Write using the callable

#### `NullLogger`

Discards all log messages:

- `static create(array $config): LoggerInterface` - Create from configuration
- `protected write(string $level, string $message, array $context = []): void` - Do nothing

### Formatter Classes

#### `LineFormatter`

Formats log messages as text lines:

- `__construct(?string $format, ?string $dateFormat)` - Constructor
- `format(string $level, string $message, array $context = []): string` - Format a log message
- `protected interpolateMessage(string $message, array $context = []): string` - Replace placeholders with context values
- `protected formatContext(array $context): string` - Format context as string

#### `JsonFormatter`

Formats log messages as JSON objects:

- `format(string $level, string $message, array $context = []): string` - Format a log message as JSON

### Manager Classes

#### `LogManager`

Manages multiple logging channels:

- `__construct(array $config = [])` - Constructor
- `channel(?string $channel = null): LoggerInterface` - Get a logger by channel name
- `protected createLogger(string $channel): LoggerInterface` - Create a new logger
- `protected discoverLoggerClass(string $driver): ?string` - Auto-discover logger class
- `protected createLoggerFromClass(string $class, array $config): LoggerInterface` - Create logger from class
- `protected createGroupLogger(array $config): GroupLogger` - Create a group logger
- `protected createFormatter(array $config): FormatterInterface` - Create a formatter
- `registerDriver(string $driver, string $class): self` - Register a custom driver
- `registerNamespace(string $namespace): self` - Register a namespace for auto-discovery
- `getChannels(): array` - Get available channel names
- `hasChannel(string $channel): bool` - Check if a channel exists
- `addChannel(string $channel, array $config): self` - Add a new channel
- `getDriverMap(): array` - Get registered driver mappings
- `getNamespaces(): array` - Get registered namespaces

#### `SwooleLogManager`

Extended LogManager with Swoole support:

- `protected createFileLogger(array $config, FormatterInterface $formatter): FileLogger` - Create a Swoole-aware file logger
- `createSwooleTableLogger(array $config, FormatterInterface $formatter): SwooleTableLogger` - Create a Swoole table logger
- `protected createLogger(string $channel): SwooleTableLogger` - Handle Swoole-specific drivers

## Configuration Examples

### Basic Configuration

```php
$config = [
    'default' => 'stack',  // Default channel
    'channels' => [
        'stack' => [
            'driver' => 'group',
            'channels' => ['file', 'stdout'],
            'level' => 'debug'
        ],
        'file' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'debug',
            'formatter' => 'line',
            'format' => "[%datetime%] [%level%] %message% %context%",
            'date_format' => 'Y-m-d H:i:s',
            'rotate' => true,
            'max_file_size' => 10485760  // 10MB
        ],
        'daily' => [
            'driver' => 'file',
            'path' => 'logs/daily-',  // Will append date automatically
            'level' => 'info',
            'formatter' => 'line'
        ],
        'stdout' => [
            'driver' => 'stream',
            'stream' => 'php://stdout',
            'level' => 'debug',
            'formatter' => 'line'
        ],
        'error' => [
            'driver' => 'file',
            'path' => 'logs/error.log',
            'level' => 'error',
            'formatter' => 'json'
        ]
    ]
];
```

### Swoole Configuration

```php
$config = [
    'default' => 'swoole_table',
    'channels' => [
        'swoole_table' => [
            'driver' => 'swoole_table',
            'max_entries' => 10000,
            'level' => 'debug',
            'formatter' => 'json'
        ],
        'file' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'info',
            'formatter' => 'line',
            'rotate' => true
        ]
    ]
];
```