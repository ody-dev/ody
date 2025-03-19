<?php

use Ody\Foundation\Facades\Route;

Route::post('/auth/login', 'Ody\Auth\Controllers\AuthController@login');
Route::post('/auth/register', 'Ody\Auth\Controllers\AuthController@register');
Route::post('/auth/refresh', 'Ody\Auth\Controllers\AuthController@refresh');

// Protected authentication endpoints
Route::group(['prefix' => '/auth', 'middleware' => ['auth']], function ($router) {
    $router->get('/user', 'Ody\Auth\Controllers\AuthController@user');
    $router->post('/logout', 'Ody\Auth\Controllers\AuthController@logout');
});