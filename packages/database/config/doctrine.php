<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Entity Paths
    |--------------------------------------------------------------------------
    |
    | The paths where your entity classes are located.
    |
    */
    'entity_paths' => [
        app_path('Entities'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy Directory
    |--------------------------------------------------------------------------
    |
    | The directory where Doctrine generates proxy classes.
    |
    */
    'proxy_dir' => storage_path('proxies'),

    /*
    |--------------------------------------------------------------------------
    | Naming Strategy
    |--------------------------------------------------------------------------
    |
    | The naming strategy to use for entity classes.
    | Available options:
    | - Doctrine\ORM\Mapping\UnderscoreNamingStrategy
    | - Doctrine\ORM\Mapping\DefaultNamingStrategy
    |
    */
    'naming_strategy' => \Doctrine\ORM\Mapping\UnderscoreNamingStrategy::class,

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the cache adapter used by Doctrine.
    |
    */
    'cache' => [
        // Cache type: array, file, redis
        'type' => env('DOCTRINE_CACHE_TYPE', 'array'),

        // TTL for file and redis cache (in seconds)
        'ttl' => env('DOCTRINE_CACHE_TTL', 3600),

        // Directory for file cache
        'directory' => storage_path('cache/doctrine'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Types
    |--------------------------------------------------------------------------
    |
    | Register your custom Doctrine types here.
    |
    */
    'types' => [
        'json' => \Ody\DB\Doctrine\Types\JsonType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event System
    |--------------------------------------------------------------------------
    |
    | Configure the Doctrine event system.
    |
    */
    'enable_events' => env('DOCTRINE_ENABLE_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Event Subscribers
    |--------------------------------------------------------------------------
    |
    | Register custom event subscribers.
    |
    */
    'event_subscribers' => [
        // Add your custom event subscribers here
        // Example: App\Doctrine\Events\CustomSubscriber::class,
    ],
];