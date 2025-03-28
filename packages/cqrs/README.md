# ODY CQRS

A robust and flexible CQRS (Command Query Responsibility Segregation) implementation for the ODY PHP framework with
Swoole coroutine support.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Overview

ODY CQRS provides a clean separation between commands (that modify state) and queries (that return data) in your
application. This implementation leverages Swoole's coroutines for high-performance asynchronous processing and
integrates with message queues for reliable event-driven architecture.

Key features:

- **Command Bus**: Process state-changing operations
- **Query Bus**: Handle data retrieval operations
- **Event Bus**: Broadcast and handle domain events
- **Middleware Support**: Extend functionality with custom middleware
- **Asynchronous Processing**: Offload commands and events to background queue
- **Attribute-based Registration**: Simple handler declaration with PHP 8 attributes

## Installation

```bash
composer require ody/cqrs
```

Requirements:

- PHP 8.3+
- Swoole extension 6.0+
- RabbitMQ (other message brokers will be released soon)

## Quick Start

### 1. Define Commands, Queries, and Events

```php
<?php
// Command to modify state
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

// Query to retrieve data
namespace App\Queries;

use Ody\CQRS\Message\Query;

class GetUserById extends Query
{
    public function __construct(
        private string $id
    ) {}

    public function getId(): string
    {
        return $this->id;
    }
}

// Event to notify about domain changes
namespace App\Events;

use Ody\CQRS\Message\Event;

class UserWasCreated extends Event
{
    public function __construct(
        private string $id
    ) {}

    public function getId(): string
    {
        return $this->id;
    }
}
```

### 2. Create Handlers

```php
<?php
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

### 3. Use in Controllers

```php
<?php
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
        private readonly QueryBus   $queryBus
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

### 4. Configure CQRS

Create or update `config/cqrs.php`:

```php
<?php
return [
    'async_enabled' => env('CQRS_ASYNC_ENABLED', true),
    'async_commands' => [
        // List command classes that should run asynchronously
        App\Commands\ProcessPaymentCommand::class,
    ],
    'handler_paths' => [
        app_path('Services'),
    ],
    'swoole' => [
        'enabled' => env('CQRS_SWOOLE_ENABLED', true),
        'max_coroutines' => env('CQRS_SWOOLE_MAX_COROUTINES', 3000),
    ],
];
```

## Advanced Configuration

The CQRS module includes numerous configuration options:

```php
<?php
return [
    // Enable/disable asynchronous processing
    'async_enabled' => env('CQRS_ASYNC_ENABLED', true),
    
    // Specify which commands should be processed asynchronously
    'async_commands' => [
        // If empty, all commands will be async when async_enabled is true
        // App\Commands\ProcessPaymentCommand::class,
    ],
    
    // Default message queue topics
    'default_command_topic' => env('CQRS_COMMAND_TOPIC', 'commands'),
    'default_event_topic' => env('CQRS_EVENT_TOPIC', 'events'),
    
    // Custom queue topics for specific commands
    'command_topics' => [
        App\Commands\ProcessPaymentCommand::class => 'payment_commands',
    ],
    
    // Custom queue topics for specific events
    'event_topics' => [
        App\Events\PaymentProcessedEvent::class => 'payment_events',
    ],
    
    // Paths to scan for handlers
    'handler_paths' => [
        app_path('Services'),
    ],
    
    // Swoole coroutine configuration
    'swoole' => [
        'enabled' => env('CQRS_SWOOLE_ENABLED', true),
        'max_coroutines' => env('CQRS_SWOOLE_MAX_COROUTINES', 3000),
    ],
];
```

## Middleware

You can add custom middleware to each bus for tasks like logging, validation, or authentication.

### Example: Command Bus Middleware

```php
<?php
namespace App\Middleware;

use Ody\CQRS\Bus\Middleware\CommandBusMiddleware;

class LoggingMiddleware extends CommandBusMiddleware
{
    public function handle(object $command, callable $next): void
    {
        logger()->info('Handling command: ' . get_class($command));
        
        // Execute the next middleware or the final handler
        $next($command);
        
        logger()->info('Command handled: ' . get_class($command));
    }
}

// Register middleware
$commandBus->addMiddleware(new LoggingMiddleware());
```

## Asynchronous Processing

When async processing is enabled, commands are sent to message queues and processed in the background:

1. Command is dispatched in your application
2. Command is serialized and sent to the message queue
3. CommandProcessor picks up and executes the command asynchronously
4. Results and events are processed accordingly

This approach improves application responsiveness, especially for time-consuming operations.

## Working with Swoole Coroutines

This CQRS implementation leverages Swoole's coroutines for better performance:

1. Multiple commands and queries can be processed concurrently
2. System resources are used more efficiently
3. Handlers can perform non-blocking I/O operations

To take advantage of coroutines in your handlers:

```php
#[CommandHandler]
public function processLargeData(ProcessLargeDataCommand $command)
{
    // Create a new coroutine for heavy processing
    go(function () use ($command) {
        // Long-running process here
        $this->processDataInBackground($command->getData());
    });
    
    return true; // Return immediately
}
```

## Best Practices

1. **Keep Commands and Queries Simple**: They should be DTOs (Data Transfer Objects) without complex logic
2. **Single Responsibility**: Each handler should handle one specific command or query
3. **Domain Events**: Use events to notify about state changes, not to perform side effects
4. **Idempotency**: Design command handlers to be idempotent (can be executed multiple times with the same result)
5. **Transactions**: Use database transactions in command handlers to ensure atomicity

## License

This project is licensed under the MIT License - see the LICENSE file for details.