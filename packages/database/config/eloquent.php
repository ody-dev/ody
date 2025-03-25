<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Eloquent Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Eloquent ORM connections
    | including connection pool settings and model options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work.
    |
    */
    'default' => env('ELOQUENT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connection Pool Settings
    |--------------------------------------------------------------------------
    |
    | Configure the Swoole-based connection pool behavior for Eloquent.
    |
    */
    'pool' => [
        'enabled' => env('ELOQUENT_POOL_ENABLED', true),
        'size' => env('ELOQUENT_POOL_SIZE', 32),
        'idle_timeout' => env('ELOQUENT_IDLE_TIMEOUT', 60.0),
        'max_lifetime' => env('ELOQUENT_MAX_LIFETIME', 3600.0),
        'borrowing_timeout' => env('ELOQUENT_BORROWING_TIMEOUT', 0.5),
        'returning_timeout' => env('ELOQUENT_RETURNING_TIMEOUT', 0.1),
        'leak_detection_threshold' => env('ELOQUENT_LEAK_DETECTION_THRESHOLD', 10.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
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
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'pooling' => [
                'pool_name' => 'eloquent-default', // Unique pool name for this connection
            ],
        ],

        'analytics' => [
            'driver' => env('DB_ANALYTICS_DRIVER', 'mysql'),
            'host' => env('DB_ANALYTICS_HOST', 'localhost'),
            'port' => env('DB_ANALYTICS_PORT', '3306'),
            'database' => env('DB_ANALYTICS_DATABASE', 'analytics'),
            'username' => env('DB_ANALYTICS_USERNAME', 'analytics'),
            'password' => env('DB_ANALYTICS_PASSWORD', ''),
            'unix_socket' => env('DB_ANALYTICS_SOCKET', ''),
            'charset' => env('DB_ANALYTICS_CHARSET', 'utf8mb4'),
            'collation' => env('DB_ANALYTICS_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_ANALYTICS_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'pooling' => [
                'pool_name' => 'eloquent-analytics', // Unique pool name for this connection
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */
    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Model Options
    |--------------------------------------------------------------------------
    |
    | Configure global options for Eloquent models.
    |
    */
    'models' => [
        // Prevent lazy loading to catch N+1 query problems
        'prevent_lazy_loading' => env('ELOQUENT_PREVENT_LAZY_LOADING', true),

        // Whether to cache model database checks
        'cache_model_exists' => env('ELOQUENT_CACHE_MODEL_EXISTS', true),
    ],
];