<?php

return [
    'charset' => 'utf8mb4',
    'environments' => [
        'local' => [
            'adapter' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'db_name' => env('DB_DATABASE', 'ody'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => '',
            'options' => [
                // PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_CASE,
                PDO::CASE_LOWER,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_DIRECT_QUERY => false,
                // Max packet size for large data transfers
                // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 16777216, // 16MB
            ],
            'pool' => [
                'enabled' => env('DB_ENABLE_POOL', false),
                'connections_per_worker' => 128,
                'minimum_idle' => 128,
                'idle_timeout' => 60.0,
                'max_lifetime' => 3600.0,
                'borrowing_timeout' => 2,
                'returning_timeout' => 1,
                'leak_detection_threshold' => 10.0,
                'keep_alive_check_interval' => 60.0, // Has to be lower than MySQL wait_timeout
            ]
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => 'production_host',
            'port' => 3306,
            'username' => 'user',
            'password' => 'pass',
            'db_name' => 'my_production_db',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'pool' => [
                'enabled' => env('DB_ENABLE_POOL', false),
                'connections_per_worker' => 64,
                'minimum_idle' => 32,
                'idle_timeout' => 60.0,
                'max_lifetime' => 3600.0,
                'borrowing_timeout' => 5,
                'returning_timeout' => 1,
                'leak_detection_threshold' => 10.0,
                'keep_alive_check_interval' => 60.0, // Has to be lower than MySQL wait_timeout
            ]
        ],
    ],
    'default_environment' => 'local',
    'log_table_name' => 'migrations_log',
    'migration_dirs' => [
        'migrations' => 'database/migrations',
    ],
];
