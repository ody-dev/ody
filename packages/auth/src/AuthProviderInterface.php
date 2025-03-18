<?php

namespace Ody\Auth;

/**
 * Auth Provider Interface
 * This defines the contract for all authentication providers
 */
interface AuthProviderInterface
{
    /**
     * Authenticate a user with credentials
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return array|false User data or false if authentication failed
     */
    public function authenticate(string $username, string $password);

    /**
     * Validate a token
     *
     * @param string $token JWT token
     * @return array|false Decoded token data or false if invalid
     */
    public function validateToken(string $token);

    /**
     * Refresh a token
     *
     * @param string $refreshToken Refresh token
     * @return array|false New token pair or false if invalid
     */
    public function refreshToken(string $refreshToken);

    /**
     * Get a user by ID
     *
     * @param mixed $id User ID
     * @return array|false User data or false if not found
     */
    public function getUser($id);

    /**
     * Revoke a token
     *
     * @param string $token Token to revoke
     * @return bool Success status
     */
    public function revokeToken(string $token);
}







