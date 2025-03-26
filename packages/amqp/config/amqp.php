<?php

return [
    // Master switch to enable/disable the AMQP functionality
    'enable' => true,

    // Default connection pool
    'default' => [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => '/',

        // Connection handling
        'concurrent' => [
            'limit' => 10,  // Max concurrent consumers per process
        ],

        // Connection pool settings
        'pool' => [
            'connections' => 5,  // Number of connections per worker
        ],

        // Connection parameters
        'params' => [
            'connection_timeout' => 3.0,
            'read_write_timeout' => 3.0,
            'heartbeat' => 60,
            'keepalive' => true,
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'locale' => 'en_US',
        ],

        // Pool management settings
        'idle_timeout' => 60.0,
        'max_lifetime' => 3600.0,
        'borrowing_timeout' => 0.5,
        'returning_timeout' => 0.1,
        'leak_detection_threshold' => 10.0,
    ],

    // You can define multiple connection pools
    'analytics' => [
        'host' => 'analytics-rabbitmq',
        // Other connection settings
    ],

    // Broker-specific configuration
    'broker' => 'rabbitmq',

    // Producer defaults
    'producer' => [
        'retry' => [
            'max_attempts' => 3,
            'initial_interval' => 1000,  // ms
            'multiplier' => 2.0,
            'max_interval' => 10000,  // ms
        ],
    ],

    // Consumer defaults
    'consumer' => [
        'prefetch_count' => 10,
        'auto_declare' => true,  // Automatically declare exchanges/queues
        'auto_setup' => true,    // Set up bindings automatically
    ],

    // Process configuration
    'process' => [
        'enable' => true,
        'max_consumers' => 10,
        'auto_restart' => true,
    ]
];