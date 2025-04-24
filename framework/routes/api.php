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
use Ody\Foundation\Router\Router;

/** @var Router $router */

$router->get('/users', GetUsersHandler::class);
$router->get('/users/{id}', GetUserHandler::class);
$router->post('/users', CreateUserHandler::class);
