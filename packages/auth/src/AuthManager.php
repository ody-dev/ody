<?php

namespace Ody\Auth;

use Exception;

/**
 * Auth Manager
 * Main interface for authentication in your framework
 */
class AuthManager
{
    protected AuthProviderInterface $provider;

    public function __construct(AuthProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Authenticate a user with credentials
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return array|false User data with tokens or false
     * @throws Exception
     */
    public function login(string $username, string $password)
    {
        $user = $this->provider->authenticate($username, $password);

        if (!$user) {
            return false;
        }

        // Get tokens
        $tokens = $this->generateTokens($user);

        if (!$tokens) {
            return false;
        }

        return array_merge($user, $tokens);
    }

    /**
     * Generate tokens for a user
     *
     * @param array $user User data
     * @return array Tokens or false on failure
     * @throws Exception
     */
    protected function generateTokens(array $user): array
    {
        // This depends on your implementation, but typically:
        if (method_exists($this->provider, 'generateTokens')) {
            return $this->provider->generateTokens($user);
        }

        throw new Exception('Unable to generate tokens.');
    }

    /**
     * Validate a token and get user data
     *
     * @param string $token JWT token
     * @return array User data or false
     */
    public function validateToken(string $token): array
    {
        return $this->provider->validateToken($token);
    }

    /**
     * Refresh a token
     *
     * @param string $refreshToken Refresh token
     * @return array|false New token pair or false
     */
    public function refreshToken(string $refreshToken): array|false
    {
        return $this->provider->refreshToken($refreshToken);
    }

    /**
     * Logout a user by revoking their token
     *
     * @param string $token JWT token
     * @return bool Success status
     */
    public function logout(string $token): bool
    {
        return $this->provider->revokeToken($token);
    }

    /**
     * Get user data by ID
     *
     * @param mixed $id User ID
     * @return array|false User data or false
     */
    public function getUser(mixed $id): false|array
    {
        return $this->provider->getUser($id);
    }
}