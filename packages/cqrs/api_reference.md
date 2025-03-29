# ODY CQRS API Specification

This document outlines the API for the ODY CQRS module, which provides a synchronous implementation of the Command Query
Responsibility Segregation pattern.

## Core Interfaces

### CommandBus

The CommandBus is responsible for dispatching commands to their appropriate handlers.

```php
namespace Ody\CQRS\Interfaces;

interface CommandBus
{
    /**
     * Dispatches a command to its handler
     *
     * @param object $command The command to dispatch
     * @return void
     * @throws HandlerNotFoundException When no handler is found for the command
     * @throws CommandHandlerException When an error occurs in the handler
     */
    public function dispatch(object $command): void;
}
```

### QueryBus

The QueryBus is responsible for dispatching queries to their handlers and returning the results.

```php
namespace Ody\CQRS\Interfaces;

interface QueryBus
{
    /**
     * Dispatches a query to its handler and returns the result
     *
     * @param object $query The query to dispatch
     * @return mixed The result of the query
     * @throws HandlerNotFoundException When no handler is found for the query
     * @throws QueryHandlerException When an error occurs in the handler
     */
    public function dispatch(object $query): mixed;
}
```

### EventBus

The EventBus is responsible for publishing events to all registered event handlers.

```php
namespace Ody\CQRS\Interfaces;

interface EventBus
{
    /**
     * Publishes an event to all registered handlers
     *
     * @param object $event The event to publish
     * @return void
     */
    public function publish(object $event): void;
}
```

## Message Classes

### Command

Base class for all commands.

```php
namespace Ody\CQRS\Message;

abstract class Command implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        $data = get_object_vars($this);
        $data['__class'] = get_class($this);
        return $data;
    }

    public static function fromArray(array $data): self
    {
        $class = $data['__class'] ?? static::class;
        unset($data['__class']);

        return new $class(...$data);
    }
}
```

### Query

Base class for all queries.

```php
namespace Ody\CQRS\Message;

class Query
{
    // Base class for queries - can be extended as needed
}
```

### Event

Base class for all events.

```php
namespace Ody\CQRS\Message;

class Event
{
    // Base class for events - can be extended as needed
}
```

## Attributes

### CommandHandler

Attribute to mark a method as a command handler.

```php
namespace Ody\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class CommandHandler
{
}
```

### QueryHandler

Attribute to mark a method as a query handler.

```php
namespace Ody\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class QueryHandler
{
}
```

### EventHandler

Attribute to mark a method as an event handler.

```php
namespace Ody\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class EventHandler
{
}
```

## Handler Registry

The framework includes registries for handlers that maintain the mapping between messages and their handlers.

### HandlerRegistry

Base class for all handler registries.

```php
namespace Ody\CQRS\Handler\Registry;

class HandlerRegistry
{
    /**
     * Register a handler for a message class
     *
     * @param string $messageClass
     * @param array $handlerInfo
     * @return void
     */
    public function register(string $messageClass, array $handlerInfo): void;

    /**
     * Check if a handler exists for a message class
     *
     * @param string $messageClass
     * @return bool
     */
    public function hasHandlerFor(string $messageClass): bool;

    /**
     * Get the handler for a message class
     *
     * @param string $messageClass
     * @return array|null
     */
    public function getHandlerFor(string $messageClass): ?array;
}
```

### CommandHandlerRegistry

Registry for command handlers.

```php
namespace Ody\CQRS\Handler\Registry;

class CommandHandlerRegistry extends HandlerRegistry
{
    /**
     * Register a command handler
     *
     * @param string $commandClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $commandClass,
        string $handlerClass,
        string $handlerMethod
    ): void;
}
```

### QueryHandlerRegistry

Registry for query handlers.

```php
namespace Ody\CQRS\Handler\Registry;

class QueryHandlerRegistry extends HandlerRegistry
{
    /**
     * Register a query handler
     *
     * @param string $queryClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $queryClass,
        string $handlerClass,
        string $handlerMethod
    ): void;
}
```

### EventHandlerRegistry

Registry for event handlers. Unlike command and query handlers, multiple handlers can be registered for each event.

```php
namespace Ody\CQRS\Handler\Registry;

class EventHandlerRegistry
{
    /**
     * Register an event handler
     *
     * @param string $eventClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $eventClass,
        string $handlerClass,
        string $handlerMethod
    ): void;

    /**
     * Check if any handlers exist for an event class
     *
     * @param string $eventClass
     * @return bool
     */
    public function hasHandlersFor(string $eventClass): bool;

    /**
     * Get all handlers for an event class
     *
     * @param string $eventClass
     * @return array
     */
    public function getHandlersFor(string $eventClass): array;
}
```

## Handler Resolvers

The handler resolvers are responsible for creating callable handlers from handler information.

### HandlerResolver

Base class for handler resolvers.

```php
namespace Ody\CQRS\Handler\Resolver;

abstract class HandlerResolver
{
    /**
     * Resolves a handler from the handler info
     *
     * @param array $handlerInfo
     * @return callable
     */
    public function resolveHandler(array $handlerInfo): callable;
}
```

### CommandHandlerResolver

Resolver for command handlers. Provides additional functionality to inject an EventBus if needed.

```php
namespace Ody\CQRS\Handler\Resolver;

class CommandHandlerResolver extends HandlerResolver
{
    /**
     * Resolves a command handler from the handler info
     * Injects EventBus as a second parameter if the handler expects it
     *
     * @param array $handlerInfo
     * @return callable
     */
    public function resolveHandler(array $handlerInfo): callable;
}
```

### QueryHandlerResolver

Resolver for query handlers.

```php
namespace Ody\CQRS\Handler\Resolver;

class QueryHandlerResolver extends HandlerResolver
{
    // Uses the base implementation from HandlerResolver
}
```

## Middleware

Middleware classes allow for extending the functionality of the buses.

### CommandBusMiddleware

Base class for command bus middleware.

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class CommandBusMiddleware
{
    /**
     * Handle the command
     *
     * @param object $command
     * @param callable $next
     * @return void
     */
    abstract public function handle(object $command, callable $next): void;
}
```

### QueryBusMiddleware

Base class for query bus middleware.

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class QueryBusMiddleware
{
    /**
     * Handle the query
     *
     * @param object $query
     * @param callable $next
     * @return mixed
     */
    abstract public function handle(object $query, callable $next): mixed;
}
```

### EventBusMiddleware

Base class for event bus middleware.

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class EventBusMiddleware
{
    /**
     * Handle the event
     *
     * @param object $event
     * @param callable $next
     * @return void
     */
    abstract public function handle(object $event, callable $next): void;
}
```

## Exceptions

### HandlerNotFoundException

Thrown when no handler is found for a message.

```php
namespace Ody\CQRS\Exception;

class HandlerNotFoundException extends \Exception
{
}
```

### CommandHandlerException

Thrown when an error occurs in a command handler.

```php
namespace Ody\CQRS\Exception;

class CommandHandlerException extends \Exception
{
}
```

### QueryHandlerException

Thrown when an error occurs in a query handler.

```php
namespace Ody\CQRS\Exception;

class QueryHandlerException extends \Exception
{
}
```

## Configuration

Configuration is done through the `config/cqrs.php` file:

```php
return [
    // Paths to scan for handlers
    'handler_paths' => [
        app_path('Services'),
    ],
];
```

## Service Provider

The `CQRSServiceProvider` class is responsible for registering the CQRS services with the application container:

- Registers command, query, and event bus implementations
- Registers handler registries and resolvers
- Scans for handlers in the configured paths
- Registers handlers with the appropriate registries

## Integration with Swoole

While the CQRS implementation is synchronous, it can still leverage Swoole's coroutines:

1. Handlers can use Swoole's non-blocking IO operations
2. The application remains responsive during handler execution
3. Multiple handlers can run concurrently using Swoole's scheduler