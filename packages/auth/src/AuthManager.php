<?php

namespace Ody\Auth;
/**
 * Auth Manager
 * Main interface for authentication in your framework
 */
class AuthManager
{
    protected $provider;

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
     */
    public function login(string $username, string $password)
    {
        $user = $this->provider->authenticate($username, $password);

        if (!$user) {
            return false;
        }

        // Get tokens
        $tokens = $this->provider->generateTokens($user);

        if (!$tokens) {
            return false;
        }

        return array_merge($user, $tokens);
    }

    /**
     * Validate a token and get user data
     *
     * @param string $token JWT token
     * @return array|false User data or false
     */
    public function validateToken(string $token)
    {
        return $this->provider->validateToken($token);
    }

    /**
     * Refresh a token
     *
     * @param string $refreshToken Refresh token
     * @return array|false New token pair or false
     */
    public function refreshToken(string $refreshToken)
    {
        return $this->provider->refreshToken($refreshToken);
    }

    /**
     * Logout a user by revoking their token
     *
     * @param string $token JWT token
     * @return bool Success status
     */
    public function logout(string $token)
    {
        return $this->provider->revokeToken($token);
    }

    /**
     * Get user data by ID
     *
     * @param mixed $id User ID
     * @return array|false User data or false
     */
    public function getUser($id)
    {
        return $this->provider->getUser($id);
    }
}