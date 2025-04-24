# Getting Started

## Installation

Getting started with ODY Framework is straightforward:

```shell
composer create-project ody/ody my-app

cd my-app
cp .env.example .env

php ody server:start
```

This will create a new application in the `my-app` directory, set up the environment configuration, and start the
development server. By default, your application will be available at `http://localhost:9501`.

## Routing

ODY Framework provides a clean and intuitive routing system. Routes can be defined in the `routes/` directory:

```php
use App\Handlers\MyHandler;
use Ody\Foundation\Router\Router;

/** @var Router $router */

$router->get('/my-handler', MyHandler::class);
```

You can also define routes with closures for quick prototyping:

```php
$router->get('/hello', function (ServerRequestInterface $request) {
    return new JsonResponse(['message' => 'Hello, World!']);
});
```

## Request Handling

ODY uses PSR-15 request handlers, allowing for a standardized and modular approach to handling HTTP requests.

To create a request handler, your class needs to:

- Implement the Psr\Http\Server\RequestHandlerInterface
- Define a handle() method that accepts a ServerRequestInterface and returns a ResponseInterface

```php
<?php

namespace App\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ody\Foundation\Http\JsonResponse;

class MyHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Process the request
        
        // Return a response
        return new JsonResponse(['message' => 'Success!']);
    }
}
```

## Next Steps

After setting up your first ODY Framework application, you might want to explore:

Service Providers: Use service providers to register and configure your application's services
Dependency Injection: ODY provides a powerful DI container for managing your application's services
Middleware: Use middleware to process requests and responses in a modular way
Database Access: Connect to databases using the built-in database component

Check out our comprehensive documentation to learn more about building applications with ODY Framework.