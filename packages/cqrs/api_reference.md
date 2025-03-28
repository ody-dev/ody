# ODY CQRS API Reference

This document provides a comprehensive API reference for the ODY CQRS package, including core interfaces, classes, and
components.

## Table of Contents

- [Core Interfaces](#core-interfaces)
    - [CommandBus](#commandbus)
    - [QueryBus](#querybus)
    - [EventBus](#eventbus)
    - [ProducerInterface](#producerinterface)
- [Base Message Classes](#base-message-classes)
    - [Command](#command)
    - [Query](#query)
    - [Event](#event)
- [Attributes](#attributes)
    - [CommandHandler](#commandhandler)
    - [QueryHandler](#queryhandler)
    - [EventHandler](#eventhandler)
- [Bus Implementations](#bus-implementations)
    - [CommandBus](#commandbus-1)
    - [QueryBus](#querybus-1)
    - [EventBus](#eventbus-1)
- [Middleware](#middleware)
    - [CommandBusMiddleware](#commandbusmiddleware)
    - [QueryBusMiddleware](#querybusmiddleware)
    - [EventBusMiddleware](#eventbusmiddleware)
- [Handler Registries](#handler-registries)
    - [CommandHandlerRegistry](#commandhandlerregistry)
    - [QueryHandlerRegistry](#queryhandlerregistry)
    - [EventHandlerRegistry](#eventhandlerregistry)
- [Handler Resolvers](#handler-resolvers)
    - [CommandHandlerResolver](#commandhandlerresolver)
    - [QueryHandlerResolver](#queryhandlerresolver)
- [Enqueue Integration](#enqueue-integration)
    - [EnqueueCommandBus](#enqueuecommandbus)
    - [EnqueueQueryBus](#enqueuequerybus)
    - [EnqueueEventBus](#enqueueeventbus)
    - [CommandProcessor](#commandprocessor)
    - [Configuration](#configuration)
- [Service Provider](#service-provider)
    - [CQRSServiceProvider](#cqrsserviceprovider)
- [Exceptions](#exceptions)
    - [HandlerNotFoundException](#handlernotfoundexception)
    - [CommandHandlerException](#commandhandlerexception)
    - [QueryHandlerException](#queryhandlerexception)

## Core Interfaces

### CommandBus

```php
namespace Ody\CQRS\Interfaces;

interface CommandBus
{
    public function dispatch(object $command): void;
}
```

The `CommandBus` is responsible for dispatching commands to their appropriate handlers. Commands are used to change the
state of the application but do not return any values.

### QueryBus

```php
namespace Ody\CQRS\Interfaces;

interface QueryBus
{
    public function dispatch(object $query): mixed;
}
```

The `QueryBus` dispatches queries to their handlers and returns the results. Queries are used to retrieve data and do
not modify state.

### EventBus

```php
namespace Ody\CQRS\Interfaces;

interface EventBus
{
    public function publish(object $event): void;
}
```

The `EventBus` publishes events to all registered subscribers. Events represent something that has happened in the
system and can have multiple handlers.

### ProducerInterface

```php
namespace Ody\CQRS\Interfaces;

interface ProducerInterface
{
    public function sendCommand(string $topic, object $command): void;
    public function sendEvent(string $topic, object $event): void;
    public function sendQuery(string $topic, object $query): string;
    public function hasQueryResult(string $messageId): bool;
    public function getQueryResult(string $messageId, int $timeout = 5000): mixed;
}
```

The `ProducerInterface` provides methods for sending commands, events, and queries to message queues for asynchronous
processing.

## Base Message Classes

### Command

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

The `Command` base class includes serialization capabilities for asynchronous processing. Your application commands
should extend this class.

### Query

```php
namespace Ody\CQRS\Message;

class Query
{
    // Base query class
}
```

The `Query` base class for all query messages in your application.

### Event

```php
namespace Ody\CQRS\Message;

class Event
{
    // Base event class
}
```

The `Event` base class for all event messages in your application.

## Attributes

### CommandHandler

```php
namespace Ody\CQRS\Attributes;

#[Attribute(Attribute::TARGET_METHOD)]
class CommandHandler
{
}
```

The `CommandHandler` attribute marks a method as a handler for a specific command type.

### QueryHandler

```php
namespace Ody\CQRS\Attributes;

#[Attribute(Attribute::TARGET_METHOD)]
class QueryHandler
{
}
```

The `QueryHandler` attribute marks a method as a handler for a specific query type.

### EventHandler

```php
namespace Ody\CQRS\Attributes;

#[Attribute(Attribute::TARGET_METHOD)]
class EventHandler
{
}
```

The `EventHandler` attribute marks a method as a handler for a specific event type.

## Bus Implementations

### CommandBus

```php
namespace Ody\CQRS\Bus;

class CommandBus implements CommandBusInterface
{
    public function __construct(CommandBusInterface $bus);
    public function addMiddleware(CommandBusMiddleware $middleware): self;
    public function dispatch(object $command): void;
}
```

The `CommandBus` implementation provides middleware support for the command bus pattern.

### QueryBus

```php
namespace Ody\CQRS\Bus;

class QueryBus implements QueryBusInterface
{
    public function __construct(QueryBusInterface $bus);
    public function addMiddleware(QueryBusMiddleware $middleware): self;
    public function dispatch(object $query): mixed;
}
```

The `QueryBus` implementation provides middleware support for the query bus pattern.

### EventBus

```php
namespace Ody\CQRS\Bus;

class EventBus implements EventBusInterface
{
    public function __construct(EventBusInterface $bus);
    public function addMiddleware(EventBusMiddleware $middleware): self;
    public function publish(object $event): void;
}
```

The `EventBus` implementation provides middleware support for the event bus pattern.

## Middleware

### CommandBusMiddleware

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class CommandBusMiddleware
{
    abstract public function handle(object $command, callable $next): void;
}
```

The `CommandBusMiddleware` base class for implementing custom command bus middleware.

### QueryBusMiddleware

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class QueryBusMiddleware
{
    abstract public function handle(object $query, callable $next): mixed;
}
```

The `QueryBusMiddleware` base class for implementing custom query bus middleware.

### EventBusMiddleware

```php
namespace Ody\CQRS\Bus\Middleware;

abstract class EventBusMiddleware
{
    abstract public function handle(object $event, callable $next): void;
}
```

The `EventBusMiddleware` base class for implementing custom event bus middleware.

## Handler Registries

### CommandHandlerRegistry

```php
namespace Ody\CQRS\Handler\Registry;

class CommandHandlerRegistry extends HandlerRegistry
{
    public function registerHandler(
        string $commandClass,
        string $handlerClass,
        string $handlerMethod
    ): void;
}
```

The `CommandHandlerRegistry` stores mappings between command classes and their handlers.

### QueryHandlerRegistry

```php
namespace Ody\CQRS\Handler\Registry;

class QueryHandlerRegistry extends HandlerRegistry
{
    public function registerHandler(
        string $queryClass,
        string $handlerClass,
        string $handlerMethod
    ): void;
}
```

The `QueryHandlerRegistry` stores mappings between query classes and their handlers.

### EventHandlerRegistry

```php
namespace Ody\CQRS\Handler\Registry;

class EventHandlerRegistry
{
    public function registerHandler(
        string $eventClass,
        string $handlerClass,
        string $handlerMethod
    ): void;
    
    public function hasHandlersFor(string $eventClass): bool;
    
    public function getHandlersFor(string $eventClass): array;
    
    public function getHandlers(): array;
}
```

The `EventHandlerRegistry` stores mappings between event classes and their handlers. Unlike command and query
registries, events can have multiple handlers.

## Handler Resolvers

### CommandHandlerResolver

```php
namespace Ody\CQRS\Handler\Resolver;

class CommandHandlerResolver extends HandlerResolver
{
    public function __construct(Container $container, ?EventBus $eventBus = null);
    
    public function resolveHandler(array $handlerInfo): callable;
}
```

The `CommandHandlerResolver` resolves handler instances for commands. It can inject the `EventBus` into command handlers
as a second parameter if the handler method signature requires it.

### QueryHandlerResolver

```php
namespace Ody\CQRS\Handler\Resolver;

class QueryHandlerResolver extends HandlerResolver
{
    // Uses the base implementation from HandlerResolver
}
```

The `QueryHandlerResolver` resolves handler instances for queries.

## Enqueue Integration

### EnqueueCommandBus

```php
namespace Ody\CQRS\Enqueue;

class EnqueueCommandBus implements CommandBusInterface
{
    public function __construct(
        ProducerInterface $producer,
        CommandHandlerRegistry $handlerRegistry,
        CommandHandlerResolver $handlerResolver,
        Container $container,
        Configuration $configuration
    );
    
    public function dispatch(object $command): void;
}
```

The `EnqueueCommandBus` implements the CommandBus interface using the Enqueue library for asynchronous processing.

### EnqueueQueryBus

```php
namespace Ody\CQRS\Enqueue;

class EnqueueQueryBus implements QueryBusInterface
{
    public function __construct(
        ProducerInterface $producer,
        QueryHandlerRegistry $handlerRegistry,
        QueryHandlerResolver $handlerResolver,
        Container $container,
        Configuration $configuration
    );
    
    public function dispatch(object $query): mixed;
}
```

The `EnqueueQueryBus` implements the QueryBus interface using the Enqueue library. Currently, queries are processed
synchronously.

### EnqueueEventBus

```php
namespace Ody\CQRS\Enqueue;

class EnqueueEventBus implements EventBusInterface
{
    public function __construct(
        ProducerInterface $producer,
        EventHandlerRegistry $handlerRegistry,
        Container $container,
        Configuration $configuration
    );
    
    public function publish(object $event): void;
}
```

The `EnqueueEventBus` implements the EventBus interface using the Enqueue library for asynchronous processing.

### CommandProcessor

```php
namespace Ody\CQRS\Enqueue;

class CommandProcessor implements Processor, TopicSubscriberInterface
{
    public function __construct(
        CommandHandlerRegistry $registry,
        CommandHandlerResolver $resolver,
        Container $container
    );
    
    public static function getSubscribedTopics(): array;
    
    public function process(Message $message, Context $context): string;
}
```

The `CommandProcessor` processes commands from message queues in the background.

### Configuration

```php
namespace Ody\CQRS\Enqueue;

class Configuration
{
    public function __construct(array $config = []);
    
    public function isAsyncEnabled(): bool;
    
    public function shouldCommandRunAsync(string $commandClass): bool;
    
    public function getCommandTopic(string $commandClass): string;
    
    public function getEventTopic(string $eventClass): string;
}
```

The `Configuration` class manages configuration for the CQRS module including asynchronous processing settings.

## Service Provider

### CQRSServiceProvider

```php
namespace Ody\CQRS\Providers;

class CQRSServiceProvider extends ServiceProvider
{
    public function boot(): void;
    
    public function register(): void;
}
```

The `CQRSServiceProvider` registers all CQRS services in the application container and scans for handler classes.

## Exceptions

### HandlerNotFoundException

```php
namespace Ody\CQRS\Exception;

class HandlerNotFoundException extends \Exception
{
}
```

The `HandlerNotFoundException` is thrown when no handler is found for a command or query.

### CommandHandlerException

```php
namespace Ody\CQRS\Exception;

class CommandHandlerException extends \Exception
{
}
```

The `CommandHandlerException` is thrown when an error occurs during command handling.

### QueryHandlerException

```php
namespace Ody\CQRS\Exception;

class QueryHandlerException extends \Exception
{
}
```

The `QueryHandlerException` is thrown when an error occurs during query handling.

## Usage Examples

### Defining Messages

Commands:

```php
namespace App\Commands;

use Ody\CQRS\Message\Command;

class CreateUserCommand extends Command
{
    public function __construct(
        private string $name,
        private string $email,
        private string $password
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
```

Queries:

```php
namespace App\Queries;

use Ody\CQRS\Message\Query;

class GetUserById extends Query
{
    public function __construct(private string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}
```

Events:

```php
namespace App\Events;

use Ody\CQRS\Message\Event;

class UserWasCreated extends Event
{
    public function __construct(private string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}
```

### Implementing Handlers

```php
namespace App\Services;

use App\Commands\CreateUserCommand;
use App\Events\UserWasCreated;
use App\Models\User;
use App\Queries\GetUserById;
use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Interfaces\EventBus;

class UserService
{
    #[CommandHandler]
    public function createUser(CreateUserCommand $command, EventBus $eventBus)
    {
        $user = User::create([
            'name' => $command->getName(),
            'email' => $command->getEmail(),
            'password' => $command->getPassword(),
        ]);

        $eventBus->publish(new UserWasCreated($user->id));
    }

    #[QueryHandler]
    public function getUserById(GetUserById $query)
    {
        return User::findOrFail($query->getId());
    }

    #[EventHandler]
    public function when(UserWasCreated $event): void
    {
        logger()->info("User was created: " . $event->getId());
    }
}
```

### Using in Controllers

```php
namespace App\Controllers;

use App\Commands\CreateUserCommand;
use App\Queries\GetUserById;
use Ody\CQRS\Interfaces\CommandBus;
use Ody\CQRS\Interfaces\QueryBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus
    ) {}

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $this->commandBus->dispatch(
            new CreateUserCommand(
                name: $data['name'],
                email: $data['email'],
                password: $data['password']
            )
        );

        return $response->json([
            'status' => 'success'
        ]);
    }

    public function getUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->queryBus->dispatch(
            new GetUserById(
                id: $args['id']
            )
        );

        return $response->json($user);
    }
}
```