# Authentication System Documentation

## Overview

The authentication system provides a flexible JWT-based authentication solution for the Ody Framework. It supports both
direct authentication (within the application) and remote authentication (via a centralized auth service), allowing
applications to choose the approach that best fits their architecture.

## Core Components

### AuthProviderInterface

This interface defines the contract that all authentication providers must implement, ensuring consistent behavior
regardless of the provider used.

```php
interface AuthProviderInterface
{
    public function authenticate(string $email, string $password);
    public function validateToken(string $token);
    public function refreshToken(string $refreshToken);
    public function getUser($id);
    public function revokeToken(string $token);
    public function getJwtKey(): string;
    public function generateTokens(array $user): array;
}
```

### AuthManager

The `AuthManager` serves as the main entry point for authentication operations in your application. It delegates to the
configured provider for the actual implementation.

```php
class AuthManager
{
    public function __construct(AuthProviderInterface $provider);
    public function login(string $email, string $password);
    public function validateToken(string $token);
    public function refreshToken(string $refreshToken);
    public function logout(string $token);
    public function getUser($id);
}
```

### DirectAuthProvider

Handles authentication within the application, using a local user repository.

```php
class DirectAuthProvider implements AuthProviderInterface
{
    public function __construct($userRepository, string $jwtKey, int $tokenExpiry = 3600, int $refreshTokenExpiry = 2592000);
    // Implements all methods from AuthProviderInterface
}
```

### RemoteAuthProvider

Communicates with a centralized authentication service, ideal for microservice architectures.

```php
class RemoteAuthProvider implements AuthProviderInterface
{
    public function __construct(string $authServiceHost, int $authServicePort, string $serviceId, string $serviceSecret);
    // Implements all methods from AuthProviderInterface
}
```

### AuthFactory

The `AuthFactory` simplifies provider creation based on configuration.

```php
class AuthFactory
{
    public static function createFromConfig(array $config);
    public static function createDirectProvider($userRepository, string $jwtKey, int $tokenExpiry = 3600, int $refreshTokenExpiry = 2592000);
    public static function createRemoteProvider(string $authServiceHost, int $authServicePort, string $serviceId, string $serviceSecret);
}
```

### AuthMiddleware

PSR-15 compatible middleware that protects routes by validating JWT tokens.

## Authentication Flow

### Login Process

1. The user submits credentials (email/email and password)
2. `AuthManager::login()` calls the provider's `authenticate()` method
3. If authentication succeeds, tokens are generated using `generateTokens()`
4. The user object and tokens are returned to the client

### Token Validation

1. Client includes JWT token in the Authorization header
2. `AuthMiddleware` extracts the token and validates it using `AuthManager::validateToken()`
3. If valid, the user is attached to the request and the request continues
4. If invalid, an unauthorized response is returned

### Token Refresh

1. Client sends a refresh token to the refresh endpoint
2. `AuthManager::refreshToken()` validates the refresh token
3. If valid, new access and refresh tokens are generated and returned

### Logout

1. Client sends the access token to the logout endpoint
2. `AuthManager::logout()` revokes the token through the provider's `revokeToken()` method

## Configuration

The auth system can be configured in `config/auth.php`:

```php
return [
    'driver' => [
        'provider' => env('AUTH_PROVIDER', 'direct'),
        'jwt_key' => env('JWT_SECRET_KEY', 'your_secret_key'),
        'token_expiry' => 3600, // 1 hour
        'refresh_token_expiry' => 86400 * 30, // 30 days
        
        // Remote auth service config
        'service_host' => env('AUTH_SERVICE_HOST', 'localhost'),
        'service_port' => env('AUTH_SERVICE_PORT', 9501),
        'service_id' => env('SERVICE_ID', 'api_service'),
        'service_secret' => env('SERVICE_SECRET', 'service_secret')
    ],
    
    'middleware' => [
        'auth' => \Ody\Auth\Middleware\AuthMiddleware::class,
    ],
];
```

## User Repository Implementation

The `DirectAuthProvider` requires a user repository that implements:

```php
class UserRepository
{
    public function findByEmail(string $email);
    public function findById($id);
    public function storeRefreshToken($userId, $token);
    public function validateRefreshToken($refreshToken);
    public function isTokenRevoked($token);
}
```

## Auth Service Provider

The Auth Service Provider integrates the authentication system with the Ody Framework:

```php
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register auth components
    }

    public function boot(): void
    {
        // Register middleware
    }
}
```

## Route Protection

Routes can be protected using the auth middleware:

```php
// Protected route
Route::get('/protected', 'SomeController@method')->middleware('auth');

// Protected route group
Route::group(['middleware' => ['auth']], function ($router) {
    $router->get('/users', 'UserController@index');
    $router->get('/profile', 'UserController@profile');
});
```

## Error Handling

The authentication system handles these error scenarios:

1. Invalid credentials: Returns 401 Unauthorized
2. Invalid/expired tokens: Returns 401 Unauthorized
3. Missing tokens: Returns 401 Unauthorized
4. Invalid refresh tokens: Returns 401 Unauthorized

## Security Considerations

The authentication system implements these security best practices:

1. Passwords are never returned in user objects
2. JWT tokens have configurable expiration times
3. Refresh tokens can be revoked
4. Token validation includes signature verification
5. Auth middleware protects routes from unauthorized access

## Switching Between Authentication Modes

To switch between direct and remote authentication:

```
# .env for direct authentication
AUTH_PROVIDER=direct
JWT_SECRET_KEY=your_secret_key

# .env for remote authentication
AUTH_PROVIDER=remote
AUTH_SERVICE_HOST=auth-service
AUTH_SERVICE_PORT=9501
SERVICE_ID=web_app
SERVICE_SECRET=service_secret_key
```

## Best Practices

1. Always use HTTPS in production to protect tokens in transit
2. Set appropriate token expiration times (shorter for access tokens, longer for refresh tokens)
3. Implement token revocation for logout and security incidents
4. Use environment variables for sensitive configuration
5. Consider using a dedicated database or Redis for token storage in production

## Example Implementation

```php
// AuthController.php
public function login(ServerRequestInterface $request, ResponseInterface $response)
{
    $data = $request->getParsedBody();
    $result = $this->authManager->login($data['email'], $data['password']);
    
    if (!$result) {
        return $response->withStatus(401)->withJson(['error' => 'Invalid credentials']);
    }
    
    return $response->withJson([
        'token' => $result['token'],
        'refreshToken' => $result['refreshToken'],
        'expiresIn' => $result['expiresIn']
    ]);
}
```