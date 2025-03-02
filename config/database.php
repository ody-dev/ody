<?php

use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;

return [
    'migration_dirs' => [
        'migrations' => __DIR__ . '/../testing_migrations/migrations',
    ],
    'environments' => [
        'mysql' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'port' => '3306', // optional
            'username' => 'root',
            'password' => 'root',
            'db_name' => 'ody',
            'charset' => 'utf8mb4', // optional
        ],
        'pgsql' => [
            'adapter' => 'pgsql',
            'host' => 'localhost',
            'username' => 'postgres',
            'password' => 'root',
            'db_name' => 'ody',
            'charset' => 'utf8',
        ],
    ],
    'dependencies' => [
        UuidFactoryInterface::class => new UuidFactory(),
    ],
];
