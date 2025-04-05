<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use Ody\Foundation\Facades\Route;

Route::post('/users', 'App\Controllers\UserController@createUser');
Route::get('/users/{id}', 'App\Controllers\UserController@getUser');
