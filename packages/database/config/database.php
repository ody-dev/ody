<?php

return [
    'migration_dirs' => [
        'migrations' => 'database/migrations',
    ],
    'charset' => 'utf8mb4',
    'enable_connection_pool' => env('DB_ENABLE_POOL', false),
    'environments' => [
        'local' => [
            'adapter' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'db_name' => env('DB_DATABASE', 'ody'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci', // optional, if not set default collation for utf8mb4 is used
            'prefix'    => '',
            'pool_size' => env('DB_POOL_SIZE', 64),
            'options' => [
                // Performance tuning options
//                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_CASE,
                PDO::CASE_NATURAL,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                // Uncomment for persistent connections
//                 PDO::ATTR_PERSISTENT => true,
                // Server-side prepared statements (available in MySQL 5.1.17+)
                // Can improve query performance, especially for repeated queries
                PDO::MYSQL_ATTR_DIRECT_QUERY => false,
                // Connection encoding optimization
//                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                // Max packet size for large data transfers
                // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 16777216, // 16MB
            ],
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => 'production_host',
            'port' => 3306, // optional
            'username' => 'user',
            'password' => 'pass',
            'db_name' => 'my_production_db',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci', // optional, if not set default collation for utf8mb4 is used
        ],
    ],
    'default_environment' => 'local',
    'log_table_name' => 'migrations_log'
];