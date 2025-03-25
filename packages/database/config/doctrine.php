<?php

return [
    'orm' => [
        // Development mode - set to false in production
        'dev_mode' => env('APP_DEBUG', false),

        // Path to entity classes
        'entity_paths' => [
            base_path('app/Entities')
        ],

        // Directory where proxy classes will be generated
        'proxy_dir' => storage_path('proxies'),

        // Metadata driver configuration
        'attribute_driver' => true,  // Use PHP 8 attributes
        'annotation_driver' => false, // Use annotations

        // Cache configuration (can be configured to use Redis, etc.)
        'cache' => null,

        // Proxy configuration
        'auto_generate_proxy_classes' => env('DOCTRINE_AUTO_GENERATE_PROXIES', true),

        // Default entity namespace
        'entity_namespace' => 'App\\Entities',
    ],

    // Command-specific configuration
    'commands' => [
        'migrations' => [
            'table_name' => 'doctrine_migrations',
            'namespace' => 'App\\Migrations',
            'directory' => database_path('migrations'),
        ],
    ],
];