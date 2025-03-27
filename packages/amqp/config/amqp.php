<?php

return [
    // Master switch to enable/disable the AMQP functionality
    'enable' => true,

    // Default connection configuration
    'default' => [
        'host' => 'localhost',
        'port' => 5672,
        'user' => 'admin',
        'password' => 'password',
        'vhost' => '/',

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
    ],

    // You can define multiple connection configurations
    'analytics' => [
        'host' => 'analytics-rabbitmq',
        'port' => 5672,
        'user' => 'analytics',
        'password' => 'analytics-password',
        'vhost' => '/',
        // Other connection settings
        'params' => [
            'connection_timeout' => 3.0,
            'read_write_timeout' => 3.0,
            'heartbeat' => 60,
            'keepalive' => true,
        ],
    ],

    // Connection pooling configuration
    'pool' => [
        'enable' => true,
        'max_connections' => 20,
        'max_channels_per_connection' => 20,
        'max_idle_time' => 60,  // seconds
    ],

    // Broker-specific configuration
    'broker' => 'rabbitmq',

    // Producer defaults
    'producer' => [
        'paths' => ['app/Producers'],
        'retry' => [
            'max_attempts' => 3,
            'initial_interval' => 1000,  // ms
            'multiplier' => 2.0,
            'max_interval' => 10000,  // ms
        ],
    ],

    // Consumer defaults
    'consumer' => [
        'paths' => ['app/Consumers'],
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