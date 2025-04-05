<?php

return [
    'async' => [
        'enabled' => env('ASYNC_MESSAGING_ENABLED', true),

        // AMQP configuration for async commands
        'amqp' => [
            'exchange' => 'commands',
            'type' => 'topic',
            'queue' => 'commands',
            'connection' => 'default',
        ],

        // Maximum concurrent async commands
        'concurrency' => [
            'max_workers' => 10,
        ],

        // Channel definitions
        'channels' => [
            'default' => [
                'queue' => 'commands',
                'routing_key' => '#',
            ],
            // Add more channels as needed
        ],
    ],
];