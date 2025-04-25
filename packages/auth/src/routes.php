<?php

use Ody\Auth\Handlers\LoginHandler;
use Ody\Auth\Handlers\LogoutHandler;
use Ody\Auth\Handlers\RefreshTokenHandler;
use Ody\Foundation\Router\Router;

/** @var Router $router */
$router->post('/auth/login', LoginHandler::class);
$router->post('/auth/refresh', RefreshTokenHandler::class);

// Protected authentication endpoints
$router->group(['prefix' => '/auth', 'middleware' => ['auth']], function ($router) {
    $router->post('/logout', LogoutHandler::class);
});