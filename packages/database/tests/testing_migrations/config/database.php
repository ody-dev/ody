<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;

return [
    'migration_dirs' => [
        'migrations' => __DIR__ . '/../migrations',
    ],
    'environments' => [
        'mysql' => [
            'adapter' => 'mysql',
            'host' => getenv('ODY_MYSQL_HOST'),
            'port' => getenv('ODY_MYSQL_PORT'),
            'username' => getenv('ODY_MYSQL_USERNAME'),
            'password' => getenv('ODY_MYSQL_PASSWORD'),
            'db_name' => getenv('ODY_MYSQL_DATABASE'),
            'charset' => getenv('ODY_MYSQL_CHARSET'),
        ],
        'pgsql' => [
            'adapter' => 'pgsql',
            'host' => getenv('ODY_PGSQL_HOST'),
            'port' => getenv('ODY_PGSQL_PORT'),
            'username' => getenv('ODY_PGSQL_USERNAME'),
            'password' => getenv('ODY_PGSQL_PASSWORD'),
            'db_name' => getenv('ODY_PGSQL_DATABASE'),
            'charset' => getenv('ODY_PGSQL_CHARSET'),
        ],
    ],
    'dependencies' => [
        UuidFactoryInterface::class => new UuidFactory(),
    ],
];
