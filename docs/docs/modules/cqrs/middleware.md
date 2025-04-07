## Middleware

The CQRS middleware system allows you to intercept and modify the behavior of commands, queries, and events at various
points in their lifecycle.

## Types of Middleware

1. **Before**: Executes before the target method is called
2. **Around**: Wraps the execution of the target method
3. **After**: Executes after the target method returns successfully
4. **AfterThrowing**: Executes when the target method throws an exception

## Example: Logging Middleware

```php
<?php
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

## Pointcut Expressions

Pointcut expressions determine which methods the middleware applies to. The syntax supports:

1. **Exact Class Match**: `App\Services\UserService`
2. **Namespace Wildcard**: `App\Domain\*`
3. **Method Match**: `App\Services\UserService::createUser`
4. **Any Method Wildcard**: `App\Services\UserService::*`
5. **Global Wildcard**: `*` (matches everything)
6. **Logical Operations**: `App\Domain\* && !App\Domain\Internal\*`

## Example: Transactional Middleware

```php
<?php
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