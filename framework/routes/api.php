<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use App\Handlers\CreateUserHandler;
use App\Handlers\GetUserHandler;
use App\Handlers\GetUsersHandler;
use Ody\Foundation\Facades\Route;

Route::get('/users', GetUsersHandler::class);
Route::get('/users/{id}', GetUserHandler::class);
Route::post('/users', CreateUserHandler::class);
