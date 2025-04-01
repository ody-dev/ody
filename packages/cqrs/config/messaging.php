<?php

return [
    'async' => [
        'enabled' => env('ASYNC_MESSAGING_ENABLED', false),

        // AMQP configuration for async commands
        'amqp' => [
            'exchange' => 'async_commands',
            'type' => 'topic',
            'queue' => 'async_commands',
            'connection' => 'default',
        ],

        // Maximum concurrent async commands
        'concurrency' => [
            'max_workers' => 10,
        ],

        // Channel definitions
        'channels' => [
            'default' => [
                'queue' => 'async_commands',
                'routing_key' => '#',
            ],
            // Add more channels as needed
        ],
    ],
];