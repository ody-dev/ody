# ODY Container
## Overview

The ODY Container is a service container that manages class dependencies and performs 
dependency injection. Using a container makes it easy to manage and centralize the way 
objects are created in your application.

## Installation

```bash
composer require ody/container
```

## Basic Usage

### Creating a Container

```php
use Ody\Container\Container;

$container = new Container();
```

### Binding

#### Basic Binding

```php
// Bind an interface to a concrete implementation
$container->bind('App\Contracts\UserRepository', 'App\Repositories\DatabaseUserRepository');

// Bind with a closure
$container->bind('database', function ($container) {
    return new PDO('mysql:host=localhost;dbname=myapp', 'username', 'password');
});
```

#### Singleton Binding

Singleton bindings only resolve the object once and return the same instance on subsequent 
calls:

```php
$container->singleton('App\Services\PaymentGateway', function ($container) {
    return new App\Services\StripePaymentGateway($container->make('config'));
});
```

#### Instance Binding

You can bind an existing instance into the container:

```php
$config = new Config(['api_key' => 'your-api-key']);
$container->instance('config', $config);
```

### Resolving

```php
// Resolve from the container
$userRepository = $container->make('App\Contracts\UserRepository');

// Using array access notation
$database = $container['database'];
```

### Auto-Resolution

The container can automatically resolve classes with their dependencies:

```php
class UserController
{
    protected $repository;
    
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }
}

// The container will automatically resolve the UserRepository dependency
$controller = $container->make(UserController::class);
```

## Advanced Features

### Contextual Binding

Contextual binding allows you to specify different implementations based on the class that 
is requesting the dependency:

```php
$container->when(PhotoController::class)
          ->needs(Filesystem::class)
          ->give(LocalFilesystem::class);

$container->when(VideoController::class)
          ->needs(Filesystem::class)
          ->give(CloudFilesystem::class);
```

### Tagged Services

You can tag related bindings and then resolve them all at once:

```php
$container->bind('SpeedReport', function () {
    return new SpeedReport();
});

$container->bind('MemoryReport', function () {
    return new MemoryReport();
});

$container->tag(['SpeedReport', 'MemoryReport'], 'reports');

// Resolve all services with the 'reports' tag
$reports = $container->tagged('reports');
```

### Extending Resolved Objects

You can extend a resolved object to add additional functionality:

```php
$container->bind('logger', function () {
    return new FileLogger();
});

$container->extend('logger', function ($logger, $container) {
    return new LogDecorator($logger);
});
```

### Method Invocation with Dependency Injection

The container can call methods with automatic dependency injection:

```php
$result = $container->call([$controller, 'show'], ['id' => 1]);

// Using Class@method syntax
$result = $container->call('UserController@show', ['id' => 1]);
```

## Working with Swoole Coroutines

When working with Swoole's coroutines, you might need to scope certain bindings to the current 
coroutine:

```php
// Register a scoped binding
$container->scoped('database.connection', function ($container) {
    return new DbConnection($container->make('config'));
});

// Clear scoped instances between requests
$container->forgetScopedInstances();
```

## API Reference

### Container Methods

#### Binding

- `bind($abstract, $concrete = null, $shared = false)`: Register a binding with the container
- `singleton($abstract, $concrete = null)`: Register a shared binding
- `scoped($abstract, $concrete = null)`: Register a binding scoped to the current coroutine
- `instance($abstract, $instance)`: Register an existing instance as shared
- `extend($abstract, Closure $closure)`: Extend a resolved instance
- `bindIf($abstract, $concrete = null, $shared = false)`: Register a binding if not already registered
- `singletonIf($abstract, $concrete = null)`: Register a shared binding if not already registered
- `scopedIf($abstract, $concrete = null)`: Register a scoped binding if not already registered

#### Resolving

- `make($abstract, array $parameters = [])`: Resolve a type from the container
- `get($id)`: Resolve a type from the container (PSR-11 compatible)
- `build($concrete)`: Instantiate a concrete instance of a class
- `factory($abstract)`: Get a closure to resolve the given type
- `call($callback, array $parameters = [], $defaultMethod = null)`: Call a method with dependency injection

#### Alias and Tagging

- `alias($abstract, $alias)`: Alias a type to a different name
- `tag($abstracts, $tags)`: Assign tags to bindings
- `tagged($tag)`: Resolve all bindings for a tag

#### Contextual Binding

- `when($concrete)`: Define a contextual binding
- `addContextualBinding($concrete, $abstract, $implementation)`: Add a contextual binding

#### Lifecycle Events

- `resolving($abstract, Closure $callback = null)`: Register a resolving callback
- `afterResolving($abstract, Closure $callback = null)`: Register an after resolving callback
- `beforeResolving($abstract, Closure $callback = null)`: Register a before resolving callback

#### Container Management

- `flush()`: Clear all bindings and resolved instances
- `forgetInstance($abstract)`: Remove a resolved instance
- `forgetInstances()`: Clear all resolved instances
- `forgetScopedInstances()`: Clear all scoped instances
- `bound($abstract)`: Check if a binding exists
- `resolved($abstract)`: Check if a type has been resolved
- `has($id)`: Check if a binding exists (PSR-11 compatible)

### ContextualBindingBuilder Methods

- `needs($abstract)`: Define the abstract target that depends on the context
- `give($implementation)`: Define the implementation for the contextual binding
- `giveTagged($tag)`: Define tagged services to use as the implementation
- `giveConfig($key, $default = null)`: Define a configuration value to use as the implementation

## Exception Handling

The container throws various exceptions in different scenarios:

- `BindingResolutionException`: Thrown when a concrete cannot be built or resolved
- `CircularDependencyException`: Thrown when circular dependencies are detected
- `EntryNotFoundException`: Thrown when a binding is not found (implements PSR-11)

## Integration with ODY Framework

The `ContainerHelper` class provides utility methods for integrating the container with the ODY framework:

```php
use Ody\Container\Container;
use Ody\Container\ContainerHelper;

$container = new Container();
$config = require 'config/app.php';

// Configure the application container with basic services
$container = ContainerHelper::configureContainer($container, $config);

// Register all controllers in a directory
ContainerHelper::registerControllers($container, __DIR__ . '/app/Controllers');
```

## License

The ODY Container is open-sourced software licensed under the [MIT license](LICENSE).