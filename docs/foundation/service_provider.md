---
title: Service Providers
weight: 1
---

## Introduction

Service providers are the central component for bootstrapping and configuring your ODY framework application. They
provide a clean, organized way to register services, bindings, singletons, and run initialization code for your
application modules.

This guide explains how to use service providers effectively in your applications, including creating custom providers,
registering services, and understanding the provider lifecycle.

## Understanding Service Providers

At their core, service providers in ODY framework handle two primary functions:

1. **Registration**: Binding interfaces and classes into the container
2. **Bootstrapping**: Performing initialization tasks after all services are registered

All service providers in ODY extend the base `Ody\Foundation\Providers\ServiceProvider` class, which provides helper
methods to simplify registration and booting processes.

## The Provider Lifecycle

Service providers go through a specific lifecycle:

1. **Construction**: The provider is instantiated with a container reference
2. **Registration**: The `register()` method is called to bind services to the container
3. **Bootstrapping**: After all services are registered, the `boot()` method is called

The framework handles this process automatically, ensuring dependencies are available when needed.

## Creating a Custom Service Provider

To create a service provider, extend the base `ServiceProvider` class and implement the required methods:

```php
<?php

namespace App\Providers;

use Ody\Foundation\Providers\ServiceProvider;
use App\Services\MyService;
use App\Services\MyServiceInterface;

class MyServiceProvider extends ServiceProvider
{
    /**
     * Services to register as singletons
     */
    protected array $singletons = [
        MyService::class => null,
    ];
    
    /**
     * Services to register as aliases
     */
    protected array $aliases = [
        'my-service' => MyService::class
    ];

    /**
     * Register services
     */
    public function register(): void
    {
        // Bind an interface to a specific implementation
        $this->bind(MyServiceInterface::class, MyService::class);
        
        // Register a singleton with a custom factory
        $this->singleton('payment-gateway', function ($container) {
            $config = $container->make('config');
            return new PaymentGateway($config->get('services.payment'));
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutes(__DIR__.'/../../routes/my-module.php');
        
        // Initialize services that need to run code at startup
        $service = $this->make(MyService::class);
        $service->initialize();
    }
}
```

## Registration Methods

The base `ServiceProvider` offers several helper methods for service registration:

| Method                                                           | Description                                    |
|------------------------------------------------------------------|------------------------------------------------|
| `bind(string $abstract, $concrete = null, bool $shared = false)` | Register a binding in the container            |
| `singleton(string $abstract, $concrete = null)`                  | Register a shared binding in the container     |
| `instance(string $abstract, $instance)`                          | Register an existing instance in the container |
| `alias(string $abstract, string $alias)`                         | Create an alias for a binding                  |

## Simplified Registration with Properties

You can use properties to define common registrations:

```php
class MyServiceProvider extends ServiceProvider
{
    /**
     * Services to register as singletons
     */
    protected array $singletons = [
        MyService::class => null,               // Resolves the concrete type automatically
        LogManager::class => CustomLogger::class // Maps interface to implementation
    ];
    
    /**
     * Services to register as regular bindings
     */
    protected array $bindings = [
        TransientService::class => null
    ];
    
    /**
     * Alias mappings (alias => abstract)
     */
    protected array $aliases = [
        'log' => LogManager::class,
        'my-service' => MyService::class
    ];
    
    /**
     * Service tags for organization
     */
    protected array $tags = [
        'logging' => [
            LogManager::class,
            'log'
        ]
    ];
}
```

These properties are automatically processed during registration through the `registerCommon()` method, which is called
automatically.

## Deferred Providers

For efficiency, you can create deferred providers that only load when their services are needed:

```php
class DeferredServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     */
    protected bool $defer = true;
    
    // ...implementation...
    
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            MyDeferredService::class,
            'deferred-service'
        ];
    }
}
```

## Registering Service Providers

To use your service providers, register them in your application configuration:

```php
// config/app.php
return [
    'providers' => [
        // Framework providers
        \Ody\Foundation\Providers\ApplicationServiceProvider::class,
        \Ody\Foundation\Providers\ConfigServiceProvider::class,
        \Ody\Foundation\Providers\LoggingServiceProvider::class,
        
        // Your application providers
        \App\Providers\MyServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
    ]
];
```

## Loading Routes with Service Providers

Service providers offer a convenient way to load routes for specific modules:

```php
public function boot(): void
{
    // Load a single route file
    $this->loadRoutes(__DIR__.'/../../routes/api.php', [
        'prefix' => 'api',
        'middleware' => ['api'],
    ]);
    
    // Or load routes from a directory
    $this->loadRoutes(__DIR__.'/../../routes/module');
}
```

## Working with Configuration in Providers

You can access and modify configuration within service providers:

```php
public function register(): void
{
    $config = $this->make(Config::class);
    
    // Get configuration values
    $apiKey = $config->get('services.stripe.key');
    
    // Register services with configuration
    $this->singleton('stripe', function () use ($apiKey) {
        return new StripeClient($apiKey);
    });
}
```

## Built-in Service Providers

ODY framework includes several core service providers:

| Provider                     | Purpose                                  |
|------------------------------|------------------------------------------|
| `ApplicationServiceProvider` | Core application services and middleware |
| `ConfigServiceProvider`      | Configuration loading and management     |
| `ConsoleServiceProvider`     | Console commands for CLI tools           |
| `DatabaseServiceProvider`    | Database connections and PDO setup       |
| `EnvServiceProvider`         | Environment variable loading             |
| `ErrorServiceProvider`       | Exception handling                       |
| `FacadeServiceProvider`      | Facade registration                      |
| `LoggingServiceProvider`     | Logging services                         |
| `MiddlewareServiceProvider`  | HTTP middleware management               |
| `RouteServiceProvider`       | Route loading and registration           |

## Best Practices

1. **Single Responsibility**: Create separate providers for distinct areas of functionality
2. **Deferred Loading**: Use deferred providers for services that aren't needed on every request
3. **Registration vs Bootstrapping**: Keep binding registration in `register()` and initialization in `boot()`
4. **Organize with Tags**: Use tags to group related services for easier management
5. **Explicit Dependencies**: Use type-hinting to ensure proper dependency resolution

## Advanced: Integrating with Swoole Coroutines

When working with Swoole coroutines in your ODY application, consider these practices:

1. **Singleton Management**: Be cautious with singletons in a coroutine environment to avoid data leaks between requests

```php
// Registering a coroutine-aware singleton
$this->singleton(DatabaseConnection::class, function ($container) {
    $config = $container->make(Config::class);
    return new CoroutineAwareDatabaseConnection($config->get('database'));
});
```
