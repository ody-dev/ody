<?php

return [
    'handler_paths' => [
//        base_path('/app/Services'),
//        base_path('/app/Events'),
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