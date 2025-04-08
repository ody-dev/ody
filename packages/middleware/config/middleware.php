<?php

use Ody\Auth\Middleware\AuthMiddleware;
use Ody\Middleware\CorsMiddleware;
use Ody\Middleware\ErrorHandlerMiddleware;
use Ody\Middleware\JsonBodyParserMiddleware;

return [
    'global' => [
        ErrorHandlerMiddleware::class,
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