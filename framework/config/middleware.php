<?php

use Ody\Foundation\Middleware\CorsMiddleware;
use Ody\Foundation\Middleware\JsonBodyParserMiddleware;

return [
    'global' => [
//        ErrorHandlerMiddleware::class,
        CorsMiddleware::class,
        JsonBodyParserMiddleware::class,
    ],
    'named' => [
//        'auth' => AuthMiddleware::class,
    ],
    'groups' => [
        'api' => [
//            AuthMiddleware::class,
        ]
    ]
];