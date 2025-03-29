<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | This option controls whether command and event processing should be done
    | asynchronously. When enabled, commands will be processed in the background
    | using message queues.
    |
    */
    'async_enabled' => env('CQRS_ASYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Async Commands
    |--------------------------------------------------------------------------
    |
    | This option defines which commands should be processed asynchronously.
    | If empty, all commands will be processed asynchronously when async
    | is enabled. Add command class names to this array to selectively
    | enable async processing for specific commands.
    |
    */
    'async_commands' => [
        // List of command classes to process asynchronously
        // Example: App\Commands\ProcessPaymentCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Topics
    |--------------------------------------------------------------------------
    |
    | Default queue topics for commands and events
    |
    */
    'default_command_topic' => env('CQRS_COMMAND_TOPIC', 'commands'),
    'default_event_topic' => env('CQRS_EVENT_TOPIC', 'events'),

    /*
    |--------------------------------------------------------------------------
    | Command Topics
    |--------------------------------------------------------------------------
    |
    | Custom queue topics for specific commands
    |
    */
    'command_topics' => [
        // Example: App\Commands\ProcessPaymentCommand::class => 'payment_commands',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Topics
    |--------------------------------------------------------------------------
    |
    | Custom queue topics for specific events
    |
    */
    'event_topics' => [
        \App\Events\UserWasCreated::class => ['userWasCreated'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handler Paths
    |--------------------------------------------------------------------------
    |
    | Paths to scan for command, query, and event handlers
    |
    */
    'handler_paths' => [
        base_path('/app/Services'),
        base_path('/app/Events'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Swoole Coroutine Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Swoole coroutines
    |
    */
    'swoole' => [
        'enabled' => env('CQRS_SWOOLE_ENABLED', true),
        'max_coroutines' => env('CQRS_SWOOLE_MAX_COROUTINES', 3000),
    ],

    'dsn' => env('CQRS_DSN', 'amqp://admin:password@localhost:5672'),
];