# ODY CQRS

A robust and flexible CQRS (Command Query Responsibility Segregation) implementation for the ODY PHP
framework.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Overview

ODY CQRS provides a clean separation between commands (that modify state) and queries (that return data) in your
application. This implementation focuses on a synchronous approach for reliable and predictable execution of your
domain operations.

Key features:

- **Command Bus**: Process state-changing operations
- **Query Bus**: Handle data retrieval operations
- **Event Bus**: Broadcast and handle domain events
- **Middleware Support**: Extend functionality with custom middleware
- **Attribute-based Registration**: Simple handler declaration with PHP 8 attributes

## Installation

```bash
composer require ody/cqrs
```

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
    'handler_paths' => [
        app_path('Services'),
    ],
];
```

## Advanced Configuration

The CQRS module includes several configuration options:

```php
<?php
return [
    // Paths to scan for handlers
    'handler_paths' => [
        app_path('Services'),
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

# CQRS Middleware System

The CQRS middleware system allows you to intercept and modify the behavior of commands, queries, and events at various
points in their lifecycle. This powerful feature enables cross-cutting concerns like logging, validation, authorization,
and caching without modifying your core business logic.

## Types of Middleware

There are four types of middleware:

1. **Before**: Executes before the target method is called
2. **Around**: Wraps the execution of the target method
3. **After**: Executes after the target method returns successfully
4. **AfterThrowing**: Executes when the target method throws an exception

## Creating Middleware

Middleware classes are simple PHP classes with methods decorated with attribute annotations.

### Example: Logging Middleware

```php
namespace App\Middleware;

use Ody\CQRS\Middleware\Before;
use Ody\CQRS\Middleware\After;
use Ody\CQRS\Middleware\AfterThrowing;

class LoggingMiddleware
{
    #[Before(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
    public function logBeforeCommand(object $command): void
    {
        logger()->info('Processing command: ' . get_class($command));
    }

    #[After(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
    public function logAfterQuery(mixed $result, array $args): mixed
    {
        $query = $args[0] ?? null;
        
        if ($query) {
            logger()->info('Query processed: ' . get_class($query));
        }
        
        return $result;
    }

    #[AfterThrowing(pointcut: "Ody\\CQRS\\Bus\\EventBus::executeHandlers")]
    public function logEventException(\Throwable $exception, array $args): void
    {
        $event = $args[0] ?? null;
        
        if ($event) {
            logger()->error('Error handling event: ' . get_class($event));
        }
    }
}
```

### Example: Transactional Middleware

```php
namespace App\Middleware;

use Ody\CQRS\Middleware\Around;
use Ody\CQRS\Middleware\MethodInvocation;

class TransactionalMiddleware
{
    public function __construct(private \PDO $connection)
    {
    }

    #[Around(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
    public function transactional(MethodInvocation $invocation): mixed
    {
        $this->connection->beginTransaction();
        
        try {
            $result = $invocation->proceed();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
}
```

## Pointcut Expressions

Pointcut expressions determine which methods the middleware applies to. The syntax supports:

1. **Exact Class Match**: `App\Services\UserService`
2. **Namespace Wildcard**: `App\Domain\*`
3. **Method Match**: `App\Services\UserService::createUser`
4. **Any Method Wildcard**: `App\Services\UserService::*`
5. **Global Wildcard**: `*` (matches everything)
6. **Logical Operations**: `App\Domain\* && !App\Domain\Internal\*`

### Examples

```php
// Match any command in the Order namespace
#[Before(pointcut: "App\\Commands\\Order\\*")]
public function validateOrderCommand(object $command): void { }

// Match a specific method in a specific class
#[Around(pointcut: "App\\Services\\PaymentService::processPayment")]
public function securePaymentProcessing(MethodInvocation $invocation): mixed { }

// Match multiple patterns with logical OR
#[After(pointcut: "App\\Domain\\User\\* || App\\Domain\\Account\\*")]
public function auditUserChanges(mixed $result, array $args): mixed { }
```

## Middleware Priority

You can control the order in which middleware executes by setting a priority. Lower values run first.

```php
// Runs before other middleware
#[Before(priority: 1, pointcut: "*")]
public function highPriorityMiddleware(): void { }

// Runs after middleware with lower priority values
#[Before(priority: 100, pointcut: "*")]
public function lowPriorityMiddleware(): void { }
```

## Registering Middleware

Middleware is discovered and registered automatically from configured directories:

```php
// config/cqrs.php
return [
    // ...
    'middleware_paths' => [
        app_path('Middleware'),
    ],
    // ...
];
```

## Before Middleware

Before middleware runs before a method executes. It's useful for:

- Validation
- Authorization
- Parameter transformation
- Logging

```php
#[Before(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
public function validateCommand(object $command): void
{
    // Validate the command
    $errors = $this->validator->validate($command);
    
    if (!empty($errors)) {
        throw new ValidationException($errors);
    }
}
```

## Around Middleware

Around middleware wraps a method execution. It's useful for:

- Transactions
- Timing measurements
- Caching
- Retry logic

```php
#[Around(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
public function cacheQueryResults(MethodInvocation $invocation): mixed
{
    $args = $invocation->getArguments();
    $query = $args[0];
    
    $cacheKey = 'query:' . get_class($query) . ':' . md5(serialize($query));
    
    if ($this->cache->has($cacheKey)) {
        return $this->cache->get($cacheKey);
    }
    
    $result = $invocation->proceed();
    
    $this->cache->set($cacheKey, $result, 3600);
    
    return $result;
}
```

## After Middleware

After middleware runs after a method successfully returns. It's useful for:

- Result transformation
- Post-processing
- Logging
- Event publishing

```php
#[After(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
public function transformQueryResult(mixed $result, array $args): mixed
{
    // Transform the result
    if (is_array($result)) {
        return array_map(function ($item) {
            return $this->transformer->transform($item);
        }, $result);
    }
    
    return $this->transformer->transform($result);
}
```

## AfterThrowing Middleware

AfterThrowing middleware runs when a method throws an exception. It's useful for:

- Exception handling
- Logging errors
- Fallback strategies
- Error notification

```php
#[AfterThrowing(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
public function handleCommandException(\Throwable $exception, array $args): void
{
    $command = $args[0];
    
    logger()->error('Command failed: ' . get_class($command), [
        'command' => $command,
        'exception' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    // Notify monitoring system
    $this->alertService->sendAlert('Command failed: ' . get_class($command));
}
```

## Performance Considerations

In the Swoole environment where ODY runs, middleware registration happens once at bootstrap time, \
making the runtime overhead minimal. The middleware system is designed to be efficient:

1. **Cached Resolution**: Pointcut expressions are evaluated once and cached
2. **Minimal Reflection**: Heavy reflection work is done during bootstrap
3. **Optimized Invocation**: Method invocation chains are built efficiently

## Configuration Options

```php
// config/cqrs.php
return [
    // ...
    
    // Paths to scan for middleware classes
    'middleware_paths' => [
        app_path('Middleware'),
    ],
    
    // Middleware configuration
    'middleware' => [
        // Global middleware applied to all buses
        'global' => [
            // Example: App\Middleware\LoggingMiddleware::class,
        ],
        
        // Command bus specific middleware
        'command' => [
            // Example: App\Middleware\TransactionalMiddleware::class,
        ],
        
        // Query bus specific middleware
        'query' => [
            // Example: App\Middleware\CachingMiddleware::class,
        ],
        
        // Event bus specific middleware
        'event' => [
            // Example: App\Middleware\AsyncEventMiddleware::class,
        ],
    ],
    
    // ...
];
```

## Swoole Coroutines Integration

While the CQRS implementation itself is synchronous, you can still leverage Swoole's coroutines in your application when
using this module:

1. Multiple command and query handlers can still benefit from Swoole's coroutine scheduler
2. I/O operations within handlers can take advantage of Swoole's non-blocking capabilities
3. Your application remains responsive while handlers execute their logic

## Best Practices

1. **Keep Commands and Queries Simple**: They should be DTOs (Data Transfer Objects) without complex logic
2. **Single Responsibility**: Each handler should handle one specific command or query
3. **Domain Events**: Use events to notify about state changes, not to perform side effects
4. **Idempotency**: Design command handlers to be idempotent (can be executed multiple times with the same result)
5. **Transactions**: Use database transactions in command handlers to ensure atomicity

## License

This project is licensed under the MIT License - see the LICENSE file for details.