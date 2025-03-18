<?php

namespace Ody\Auth;

use Firebase\JWT\JWT;

/**
 * Direct Authentication Provider
 * Handles authentication directly within the application
 */
class DirectAuthProvider implements AuthProviderInterface
{
    protected $userRepository;
    protected $jwtKey;
    protected $tokenExpiry;
    protected $refreshTokenExpiry;

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
        $user = $this->userRepository->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Remove sensitive data
        unset($user['password']);

        return $user;
    }

    public function validateToken(string $token)
    {
        try {
            // Use Firebase JWT library
            $decoded = JWT::decode(
                $token,
                new \Firebase\JWT\Key($this->jwtKey, 'HS256')
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

    protected function isTokenRevoked(string $token)
    {
        // Check if token is revoked
        // In a real implementation, you would check against Redis or a database
        return false;
    }

    public function refreshToken(string $refreshToken)
    {
        try {
            $decoded = JWT::decode(
                $refreshToken,
                new \Firebase\JWT\Key($this->jwtKey, 'HS256')
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
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUser($id)
    {
        return $this->userRepository->findById($id);
    }

    protected function generateTokens(array $user)
    {
        $now = time();

        // Create token payload
        $payload = [
            'iss' => 'framework',
            'aud' => 'api',
            'sub' => $user['id'],
            'iat' => $now,
            'exp' => $now + $this->tokenExpiry,
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
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