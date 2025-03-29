# Integrating CQRS with API Layer

This guide explains how to seamlessly integrate your CQRS system with a RESTful API layer, allowing you to expose your
commands and queries as HTTP endpoints while maintaining clean architecture boundaries.

## Overview

The CQRS API integration provides:

1. **Automatic mapping** between HTTP requests and commands/queries
2. **Base controller classes** for CQRS operations
3. **Middleware components** for request/response handling
4. **OpenAPI documentation** generation
5. **Attribute-based API metadata** for commands and queries

## Components

### 1. CqrsApiAdapter

The `CqrsApiAdapter` acts as a bridge between HTTP requests and your CQRS system. It handles:

- Extracting data from request (body, route params, query params)
- Creating command/query objects from request data
- Dispatching commands/queries to appropriate buses

```php
// Example usage in a controller
$adapter = new CqrsApiAdapter($commandBus, $queryBus);
$result = $adapter->executeQuery(GetUserByIdQuery::class, $request);
```

### 2. CqrsController

The `CqrsController` is a base controller class that provides convenient methods for handling CQRS operations:

```php
class UserController extends CqrsController
{
    public function createUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->command(CreateUserCommand::class, $request);
    }
    
    public function getUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->query(GetUserByIdQuery::class, $request);
    }
}
```

### 3. RequestMappingMiddleware

The `RequestMappingMiddleware` automatically maps HTTP routes to commands and queries based on configuration:

```php
// Create route mapping configuration
$configFactory = new RequestMappingConfigFactory();
$config = $configFactory
    ->mapPostToCommand('/api/users', CreateUserCommand::class)
    ->mapGetToQuery('/api/users/{id}', GetUserByIdQuery::class)
    ->getConfig();

// Create middleware
$requestMappingMiddleware = new RequestMappingMiddleware($cqrsAdapter, $config);
```

### 4. ResponseFormattingMiddleware

The `ResponseFormattingMiddleware` standardizes API responses for CQRS operations, ensuring consistent JSON formatting:

```php
$responseFormattingMiddleware = new ResponseFormattingMiddleware($response);
```

### 5. OpenAPI Documentation

The `OpenApiGenerator` automatically generates OpenAPI/Swagger documentation from your commands and queries:

```php
$generator = new OpenApiGenerator($commandRegistry, $queryRegistry);
$openApiJson = $generator->generateJson(
    title: 'My API Documentation',
    version: '1.0.0'
);
```

### 6. ApiEndpoint Attribute

The `ApiEndpoint` attribute provides API metadata for your commands and queries:

```php
#[ApiEndpoint(
    path: '/api/users',
    method: 'POST',
    summary: 'Create a new user',
    description: 'Creates a new user with the provided information',
    tags: ['users']
)]
class CreateUserCommand extends Command
{
    // ...
}
```

## Implementation Guide

### Step 1: Add API Metadata to Commands and Queries

Use the `ApiEndpoint` attribute to document your commands and queries:

```php
#[ApiEndpoint(
    path: '/api/users',
    method: 'POST',
    summary: 'Create a new user',
    description: 'Creates a new user with the provided name, email and password',
    tags: ['users']
)]
class CreateUserCommand extends Command
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {
    }
}

#[ApiEndpoint(
    path: '/api/users/{id}',
    method: 'GET',
    summary: 'Get user by ID',
    description: 'Retrieves a user by their unique identifier',
    tags: ['users'],
    responseSchema: UserResponse::class
)]
class GetUserByIdQuery extends Query
{
    public function __construct(
        public string $id
    ) {
    }
}
```

### Step 2: Create API Controllers

Create controllers that extend the `CqrsController` base class:

```php
class UserController extends CqrsController
{
    public function createUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->command(CreateUserCommand::class, $request);
    }
    
    public function getUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->query(GetUserByIdQuery::class, $request);
    }
}
```

### Step 3: Set Up Middleware

Configure the middleware in your application:

```php
// Create the CQRS adapter
$cqrsAdapter = new CqrsApiAdapter($commandBus, $queryBus);

// Create route mapping configuration
$configFactory = new RequestMappingConfigFactory();
$config = $configFactory
    ->mapPostToCommand('/api/users', CreateUserCommand::class)
    ->mapGetToQuery('/api/users/{id}', GetUserByIdQuery::class)
    ->getConfig();

// Create middleware
$requestMappingMiddleware = new RequestMappingMiddleware($cqrsAdapter, $config);
$responseFormattingMiddleware = new ResponseFormattingMiddleware($response);

// Add middleware to your application
$app->add($responseFormattingMiddleware);
$app->add($requestMappingMiddleware);
```

### Step 4: Generate API Documentation

Create an endpoint that generates OpenAPI documentation:

```php
$app->get('/api/docs', function (ServerRequestInterface $request, ResponseInterface $response) use ($commandBus, $queryBus) {
    $generator = new OpenApiGenerator(
        $commandBus->getHandlerRegistry(),
        $queryBus->getHandlerRegistry()
    );
    
    $openApiJson = $generator->generateJson(
        title: 'My API Documentation',
        version: '1.0.0',
        description: 'API documentation for my application'
    );
    
    $response->getBody()->write($openApiJson);
    
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});
```

## Advanced Configuration

### Custom Request Mapping

For more complex scenarios, you can create custom mappings:

```php
$configFactory->createMapping('/api/complex-operation', 'post', [
    'query' => GetDataQuery::class,
    'auto_execute' => true,
    'additional_data' => [
        'includeDeleted' => true,
        'limit' => 100
    ]
]);
```

### Response Transformation

You can transform query results before sending them to the client:

```php
#[After(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
public function transformApiResponse(mixed $result, array $args): mixed
{
    $query = $args[0] ?? null;
    
    // Only transform API-bound queries
    if ($query && $this->isApiQuery($query)) {
        return $this->transformer->transform($result);
    }
    
    return $result;
}
```

### API Validation

You can add validation middleware specifically for API requests:

```php
#[Before(pointcut: "Ody\\CQRS\\Api\\CqrsApiAdapter::executeCommand")]
public function validateApiCommand(object $command): void
{
    $validator = $this->validatorFactory->getValidator($command);
    $errors = $validator->validate($command);
    
    if (count($errors) > 0) {
        throw new ApiValidationException($errors);
    }
}
```

## Best Practices

1. **Keep Commands and Queries Simple**: Design them as DTOs with clear property names
2. **Use Response Models**: Create specific response classes for queries
3. **Consistent Naming Conventions**: Use consistent naming for API endpoints
4. **Separation of Concerns**: Keep API-specific logic in controllers/middleware
5. **Document Everything**: Use attributes to thoroughly document your API

## Framework Integration

### Laravel Integration

```php
// app/Providers/CqrsApiServiceProvider.php
public function boot()
{
    $this->app->singleton(CqrsApiAdapter::class, function ($app) {
        return new CqrsApiAdapter(
            $app->make(CommandBus::class),
            $app->make(QueryBus::class)
        );
    });
    
    Route::middleware(['api'])
        ->prefix('api')
        ->group(function () {
            Route::post('/users', [UserController::class, 'createUser']);
            Route::get('/users/{id}', [UserController::class, 'getUser']);
        });
}
```

### Symfony Integration

```php
// config/services.yaml
services:
    App\Middleware\CqrsApiAdapter:
        arguments:
            $commandBus: '@Ody\CQRS\Interfaces\CommandBus'
            $queryBus: '@Ody\CQRS\Interfaces\QueryBus'
    
    App\Controller\UserController:
        arguments:
            $cqrsAdapter: '@App\Middleware\CqrsApiAdapter'
            $response: '@Symfony\Component\HttpFoundation\Response'
        tags: ['controller.service_arguments']
```

### Mezzio Integration

```php
// config/routes.php
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->post('/api/users', [
        $container->get(RequestMappingMiddleware::class),
        $container->get(ResponseFormattingMiddleware::class),
        $container->get(UserController::class)
    ]);
    
    $app->get('/api/users/{id}', [
        $container->get(RequestMappingMiddleware::class),
        $container->get(ResponseFormattingMiddleware::class),
        $container->get(UserController::class)
    ]);
};
```

## Conclusion

By integrating your CQRS system with an API layer, you can expose your domain logic as a clean, well-documented REST API
while maintaining separation of concerns. This approach makes it easy to evolve your API and domain logic
independently \
while providing a consistent interface for clients.