<?php

return [
    'default' => env('CACHE_DRIVER', 'redis'),
    'prefix' => env('CACHE_PREFIX', 'ody_'),
    'ttl' => env('CACHE_TTL', 3600),
    'drivers' => [
        'array' => [
            'ttl' => 3600, // Default TTL in seconds
        ],

        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'auth' => env('REDIS_PASSWORD', null),
            'db' => env('REDIS_DB', 0),
            'prefix' => env('CACHE_PREFIX', 'ody_cache:'),
            'ttl' => env('CACHE_TTL', 3600),
            'options' => [
                // Redis specific options
            ],
        ],

        'memcached' => [
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID', ''),
            'prefix' => env('CACHE_PREFIX', 'ody_cache:'),
            'ttl' => env('CACHE_TTL', 3600),
            'options' => [
                // Memcached specific options
            ],
        ],
    ]
];
