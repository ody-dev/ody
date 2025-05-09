<?php

use Monolog\Formatter\LineFormatter as MonologLineFormatter;
use Monolog\Handler\StreamHandler;
use Ody\Logger\Formatters\ConsoleExceptionFormatter;
use Psr\Log\LogLevel;

return [
    'default' => 'stack', // Default channel name
    'channels' => [
        // Example Monolog stdout config
        'stdout' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stdout',
            ],
            'level' => env('LOG_LEVEL', LogLevel::DEBUG),
            'formatter' => ConsoleExceptionFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] [%level_name%] %channel%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'includeStacktraces' => true,
            ],
            'processors' => [] // Optional: Monolog processors
        ],
        // Example Monolog file config
        'single' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'logs/ody_monolog.log', // Needs path resolution
            ],
            'level' => env('LOG_LEVEL', LogLevel::DEBUG),
            'formatter' => MonologLineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'includeStacktraces' => true,
            ]
        ],
        // Example Stack config
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'stdout'],
            'ignore_exceptions' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should be excluded from logging.
    | You can use wildcards (*) to match paths.
    |
    */
    'exclude_routes' => [
        // Log viewer routes
        '/api/logs/recent',
        '/api/logs/services',
        '/api/logs/levels',
        '/api/logs/service/*',

        // Health check endpoints
        '/health',
        '/ping',

        // Add any other routes that you want to exclude from logging
        '/metrics',
        '/status',
    ],
];
