# Installation

```bash
composer require ody/cqrs
```

## Configuration

Create or update `config/cqrs.php`:

```php
<?php
return [
    // Paths to scan for handlers
    'handler_paths' => [
        app_path('Services'),
    ],
    // Paths to scan for middleware
    'middleware_paths' => [
        app_path('Middleware'),
    ],
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
];
```

## Configure Service providers

Register the required service providers in your config/app.php:

```php

'providers' => [
    'http' => [
        // ... other providers
        \Ody\CQRS\Providers\CQRSServiceProvider::class,

        // ... for async handling
//        \Ody\AMQP\Providers\AMQPServiceProvider::class,
//        \Ody\CQRS\Providers\CQRSServiceProvider::class, // must me registered after AMQPServiceProvider!
//        \Ody\CQRS\Providers\AsyncMessagingServiceProvider::class,
    ],
    // Also for async handling, registers long running background processes
    'beforeServerStart' => [
//            \Ody\Process\Providers\ProcessServiceProvider::class,
//            \Ody\CQRS\Providers\CQRSServiceProvider::class,
//            \Ody\AMQP\Providers\AMQPServiceProvider::class,
    ]
],
```