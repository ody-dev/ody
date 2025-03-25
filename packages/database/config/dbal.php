<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DBAL Settings
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Doctrine DBAL connections
    | including pool settings and connection options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use
    | as your default connection for DBAL.
    |
    */
    'default' => env('DBAL_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connection Pool Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the shared connection pool behavior. You can
    | adjust the pool size and timeout settings based on your application needs.
    |
    */
    'pool' => [
        'enabled' => env('DBAL_POOL_ENABLED', true),
        'size' => env('DBAL_POOL_SIZE', 64),
        'idle_timeout' => env('DBAL_IDLE_TIMEOUT', 60.0),
        'max_lifetime' => env('DBAL_MAX_LIFETIME', 3600.0),
        'borrowing_timeout' => env('DBAL_BORROWING_TIMEOUT', 0.5),
        'returning_timeout' => env('DBAL_RETURNING_TIMEOUT', 0.1),
        'leak_detection_threshold' => env('DBAL_LEAK_DETECTION_THRESHOLD', 10.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | DBAL Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | You can configure multiple connections for different databases.
    |
    */
    'connections' => [
        'default' => [
            'driver' => env('DB_DRIVER', 'mysql'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'ody'),
            'username' => env('DB_USERNAME', 'ody'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'pooling' => [
                'pool_name' => 'dbal-default', // Unique pool name for this connection
            ],
        ],

        'analytics' => [
            'driver' => env('DB_ANALYTICS_DRIVER', 'mysql'),
            'host' => env('DB_ANALYTICS_HOST', 'localhost'),
            'port' => env('DB_ANALYTICS_PORT', '3306'),
            'database' => env('DB_ANALYTICS_DATABASE', 'analytics'),
            'username' => env('DB_ANALYTICS_USERNAME', 'analytics'),
            'password' => env('DB_ANALYTICS_PASSWORD', ''),
            'charset' => env('DB_ANALYTICS_CHARSET', 'utf8mb4'),
            'collation' => env('DB_ANALYTICS_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_ANALYTICS_PREFIX', ''),
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'pooling' => [
                'pool_name' => 'dbal-analytics', // Unique pool name for this connection
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Tools
    |--------------------------------------------------------------------------
    |
    | Configuration options for schema tools.
    |
    */
    'schema' => [
        'default_table_options' => [
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collate' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'engine' => 'InnoDB',
        ],
    ],
];