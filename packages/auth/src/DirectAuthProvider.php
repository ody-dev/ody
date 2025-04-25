<?php

namespace Ody\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Random\RandomException;

/**
 * Direct Authentication Provider
 * Handles authentication directly within the application
 */
class DirectAuthProvider implements AuthProviderInterface
{
    protected $userRepository;
    protected string $jwtKey;
    protected int $tokenExpiry;
    protected int $refreshTokenExpiry;

    public function __construct($userRepository, string $jwtKey, int $tokenExpiry = 3600, int $refreshTokenExpiry = 2592000)
    {
        $this->userRepository = $userRepository;
        $this->jwtKey = $jwtKey;
        $this->tokenExpiry = $tokenExpiry;
        $this->refreshTokenExpiry = $refreshTokenExpiry;
    }

    public function authenticate(string $username, string $password)
    {
        // Find user in repository
        $user = $this->userRepository->findByEmail($username);
        $passwordString = $this->userRepository->getAuthPassword($user['id']);

        if (!$user || !password_verify($password, $passwordString)) {
            return false;
        }

        // Remove sensitive data
        unset($user['password']);

        return $user;
    }

    /**
     * Validate a JWT token
     *
     * @param string $token JWT token
     * @return false|array Decoded token data or false on failure
     */
    public function validateToken(string $token): false|array
    {
        try {
            // Use Firebase JWT library
            $decoded = JWT::decode(
                $token,
                new Key($this->jwtKey, 'HS256')
            );

            // Check if token is in blacklist (could use Redis/DB)
            if ($this->isTokenRevoked($token)) {
                return false;
            }

            return (array)$decoded;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function isTokenRevoked(string $token): false
    {
        // Check if token is revoked
        // In a real implementation, you would check against Redis or a database
        return false;
    }

    /**
     * Refresh a JWT token using a refresh token
     *
     * @param string $refreshToken Refresh token
     * @return false|array New tokens or false on failure
     */
    public function refreshToken(string $refreshToken): false|array
    {
        $decoded = JWT::decode(
            $refreshToken,
            new Key($this->jwtKey, 'HS256')
        );

        // Verify this is a refresh token and not expired
        if (!isset($decoded->type) || $decoded->type !== 'refresh' || $decoded->exp < time()) {
            return false;
        }

        $user = $this->getUser($decoded->sub);

        if (!$user) {
            return false;
        }

        return $this->generateTokens($user);
    }

    /**
     * Get user by ID
     *
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function getUser($id): false|array
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Generate JWT and refresh token for a user
     *
     * @param array $user User data
     * @return array Tokens
     * @throws RandomException
     */
    public function generateTokens(array $user): array
    {
        $now = time();

        // Create token payload
        $payload = [
            'iss' => 'framework',
            'aud' => 'api',
            'sub' => $user['id'],
            'iat' => $now,
            'exp' => $now + $this->tokenExpiry,
            'email' => $user['email'],
            'roles' => $user['roles'] ?? []
        ];

        // Generate JWT
        $token = JWT::encode($payload, $this->jwtKey, 'HS256');

        // Create refresh token
        $refreshPayload = [
            'iss' => 'framework',
            'sub' => $user['id'],
            'iat' => $now,
            'exp' => $now + $this->refreshTokenExpiry,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)) // Unique identifier
        ];

        $refreshToken = JWT::encode($refreshPayload, $this->jwtKey, 'HS256');

        return [
            'token' => $token,
            'refreshToken' => $refreshToken,
            'expiresIn' => $this->tokenExpiry
        ];
    }

    public function revokeToken(string $token)
    {
        // Add to blacklist
        // In a real implementation, you would store this in Redis or a database
        return true;
    }
}