<?php

/** @var \Ody\Foundation\Router\Router $router */
$router->post('/auth/login', 'Ody\Auth\Controllers\AuthController@login');
$router->post('/auth/register', 'Ody\Auth\Controllers\AuthController@register');
$router->post('/auth/refresh', 'Ody\Auth\Controllers\AuthController@refresh');

// Protected authentication endpoints
$router->group(['prefix' => '/auth', 'middleware' => ['auth']], function ($router) {
    $router->get('/user', 'Ody\Auth\Controllers\AuthController@user');
    $router->post('/logout', 'Ody\Auth\Controllers\AuthController@logout');
});