# Ody Auth Module

A flexible authentication module for the ODY framework that provides JWT-based authentication with support for direct
and remote authentication providers.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net/releases/8.3/en.php)

## Features

- **PSR-15 Compliant Middleware**: Seamlessly integrate with any PSR-15 compatible framework
- **Multiple Authentication Providers**: Choose between direct (in-app) or remote (centralized service) authentication
- **JWT Token Authentication**: Secure authentication with JSON Web Tokens
- **Refresh Token Support**: Maintain user sessions with refresh tokens
- **Flexible Identity Management**: Easily retrieve and validate user identity and roles
- **Middleware-based Protection**: Protect routes with simple middleware configuration

## Installation

Install the package via Composer:

```bash
composer require ody/auth
```

## Quick Start

### Configure the Auth Module

Create or update your configuration file:

```php
// config/auth.php
return [
    'driver' => [
        'provider' => env('AUTH_PROVIDER', 'direct'),
        'jwt_key' => env('JWT_SECRET_KEY', 'your_secret_key_for_development'),
        'token_expiry' => 3600, // 1 hour
        'refresh_token_expiry' => 86400 * 30, // 30 days

        // Remote auth service config (if using 'remote' provider)
        'service_host' => env('AUTH_SERVICE_HOST', 'localhost'),
        'service_port' => env('AUTH_SERVICE_PORT', 9501),
        'service_id' => env('SERVICE_ID', 'api_service'),
        'service_secret' => env('SERVICE_SECRET', 'service_secret')
    ],

    'middleware' => [
        'auth' => \Ody\Auth\Middleware\AuthenticationMiddleware::class,
    ],
];
```

### Register the Service Provider

Add the service provider to your app.php config:

```php
'http' => [
    // ...
    \Ody\Auth\Providers\AuthServiceProvider::class,
    // ...
],
```

```php
// In your bootstrap or application setup file
$container->register(new \Ody\Auth\Providers\AuthServiceProvider($container));
```

### Implement User Repository

Implement a user repository that can find users by ID and email:

```php
namespace App\Repositories;

class UserRepository
{
    public function findByEmail(string $email)
    {
        // Retrieve user by email from database
        // Return array with user data or null
    }

    public function findById(int $id)
    {
        // Retrieve user by ID from database
        // Return array with user data or null
    }

    public function getAuthPassword($userId)
    {
        // Return the hashed password for the user
    }
}
```

### Protect Routes with Middleware

```php
// routes.php
$router->group(['prefix' => '/api', 'middleware' => ['auth']], function ($router) {
    $router->get('/profile', ProfileHandler::class);
    $router->post('/logout', LogoutHandler::class);
});

// Public routes
$router->post('/auth/login', LoginHandler::class);
$router->post('/auth/refresh', RefreshTokenHandler::class);
```

## Detailed Usage

### Authentication Flow

1. **User Login**:
    - Client sends username/password to `/auth/login`
    - Server returns JWT token and refresh token

2. **Protected Route Access**:
    - Client includes JWT token in `Authorization: Bearer {token}` header
    - Middleware validates token and attaches identity to request

3. **Token Refresh**:
    - When token expires, client uses refresh token to get a new token pair
    - Send refresh token to `/auth/refresh` to get new tokens

4. **Logout**:
    - Send request to `/auth/logout` with token
    - Server revokes token

### Accessing User Identity

In your request handlers or middleware, access the authenticated user:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    // Get the user identity from request attributes
    $identity = $request->getAttribute(AuthenticationMiddleware::IDENTITY_ATTRIBUTE);
    
    if (!$identity) {
        // User is not authenticated
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    // Access user data
    $userId = $identity->getId();
    $userRoles = $identity->getRoles();
    
    // Check user role
    $isAdmin = $identity->hasRole('admin');
    
    // Process request for authenticated user...
}
```

### Using Direct Authentication Provider

The direct provider authenticates users against your application's user repository:

```php
// Register the user repository in your container
$container->singleton('user.repository', function () {
    return new UserRepository();
});

// Configure direct auth in .env
AUTH_PROVIDER=direct
JWT_SECRET_KEY=your_secure_secret_key
```

### Using Remote Authentication Provider

The remote provider delegates authentication to a central auth service:

```php
// Configure remote auth in .env
AUTH_PROVIDER=remote
AUTH_SERVICE_HOST=auth.example.com
AUTH_SERVICE_PORT=9501
SERVICE_ID=your_service_id
SERVICE_SECRET=your_service_secret
```

### Creating Custom Auth Adapters

Implement `AdapterInterface` to create custom authentication logic:

```php
namespace App\Auth;

use Ody\Auth\AdapterInterface;
use Ody\Auth\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;

class CustomAdapter implements AdapterInterface
{
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        // Custom authentication logic
        // Return Identity object or null if authentication fails
    }
}
```

Register your custom adapter:

```php
$container->singleton(AdapterInterface::class, function () {
    return new CustomAdapter();
});
```

## API Reference

### Key Classes and Interfaces

- **AuthenticationInterface**: Core authentication service
- **AdapterInterface**: Authentication strategy interface
- **IdentityInterface**: User identity contract
- **JwtAdapter**: JWT-based authentication adapter
- **DirectAuthProvider**: Local authentication provider
- **RemoteAuthProvider**: Remote authentication provider
- **AuthenticationMiddleware**: PSR-15 authentication middleware

### Main Methods

#### Authentication

```php
// Authenticate from request
$identity = $auth->authenticate($request);

// Login with credentials
$result = $auth->login($email, $password);

// Refresh token
$newTokens = $auth->refreshToken($refreshToken);

// Logout (revoke token)
$auth->logout($token);
```

#### Identity

```php
// Get user ID
$userId = $identity->getId();

// Get user roles
$roles = $identity->getRoles();

// Check if user has role
$isAdmin = $identity->hasRole('admin');

// Get user details
$email = $identity->getDetail('email');
// Or using magic property
$email = $identity->email;
```

## Environment Variables

| Variable            | Description                                         | Default                           |
|---------------------|-----------------------------------------------------|-----------------------------------|
| `AUTH_PROVIDER`     | Authentication provider type (`direct` or `remote`) | `direct`                          |
| `JWT_SECRET_KEY`    | Secret key for JWT token signing                    | `your_secret_key_for_development` |
| `AUTH_SERVICE_HOST` | Remote auth service hostname                        | `localhost`                       |
| `AUTH_SERVICE_PORT` | Remote auth service port                            | `9501`                            |
| `SERVICE_ID`        | Service identifier for remote auth                  | `api_service`                     |
| `SERVICE_SECRET`    | Service secret for remote auth                      | `service_secret`                  |

## Security Best Practices

1. **Always use HTTPS** for production environments
2. **Set a strong JWT secret key** in production
3. **Implement token revocation** in a persistent store (Redis/database)
4. **Set appropriate token expiry times** based on your security requirements
5. **Protect sensitive routes** with proper middleware
6. **Validate user input** in login/registration handlers
7. **Use proper password hashing** in your user repository

## Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).