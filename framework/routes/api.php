<?php

/**
 * Main application routes
 *
 * This file contains all the routes for the application.
 * Variables $router, $middleware, and $container are available from the RouteLoader.
 */

use App\Handlers\GetUsersHandler;
use Ody\Foundation\Facades\Route;

Route::get('/users', GetUsersHandler::class);
