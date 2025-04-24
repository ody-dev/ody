# Routes

## Basic Route Definition

Routes are defined in `routes/*.php` files, with the main application routes typically located in `routes/api.php`.
The framework provides a fluent interface through the `Route` facade for defining routes.

```php
use Ody\Foundation\Router\Router;

/** @var Router $router */
// Basic route definition
$router->get('/endpoint', Handler::class);

// You can also use a closure as a handler
$router->get('/hello', function (ServerRequestInterface $request) {
    return new JsonResponse(['message' => 'Hello, World!']);
});
```

## Available HTTP Methods

The router supports all standard HTTP methods:

```php
$router->get('/resource', Handler::class);
$router->post('/resource', Handler::class);
$router->put('/resource', Handler::class);
$router->patch('/resource', Handler::class);
$router->delete('/resource', Handler::class);
$router->options('/resource', Handler::class);
```

## Route Parameters

You can define dynamic route parameters using curly braces:

```php
$router->get('/users/{id}', GetUserHandler::class);
```

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    // Retrieve the route parameter
    $userId = $request->getAttribute('id');
    // ...
}
```

## Route Groups

Routes can be grouped to apply common prefixes, middleware, or other attributes:

```php
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function ($router) {
    $router->get('/dashboard', GetDashboardHandler::class);
    $router->get('/users', GetUsersHandler::class);
});
```

This creates routes at `/admin/dashboard` and `/admin/users` that are both protected by the `auth` middleware.

## Middleware

Middleware can be applied to individual routes or groups of routes:

```php
// Apply to a single route
$router->get('/profile', 'UserController@profile')->middleware('auth');

// Apply to a group of routes
$router->group(['middleware' => ['auth', 'admin']], function ($router) {
    // All routes in this group will use both middleware
});
```

## Named Routes

You can assign names to routes for easier reference:

```php
$router->get('/user/profile', 'UserController@profile')->name('profile');
```

## Performance Considerations

The routing system is built on FastRoute for efficient route matching and leverages Swoole's coroutines for concurrent
request processing. This combination provides excellent performance for high-throughput API applications.

To maximize performance:

- Group related routes together
- Use middleware judiciously
- Consider the depth of your route hierarchy

## Further Reading

For more advanced routing scenarios, refer to the FastRoute documentation
at [https://github.com/nikic/FastRoute](https://github.com/nikic/FastRoute).