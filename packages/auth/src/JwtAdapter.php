<?php

namespace Ody\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class JwtAdapter implements AdapterInterface
{
    /**
     * @var string
     */
    private $jwtKey;

    /**
     * @var string
     */
    private $tokenPrefix;

    /**
     * @var string
     */
    private $headerName;

    /**
     * @var string
     */
    private $algorithm;

    /**
     * @var callable|null
     */
    private $tokenRevokedCallback;

    /**
     * JwtAdapter constructor.
     *
     * @param string $jwtKey The secret key for JWT validation
     * @param string $tokenPrefix The prefix used in the Authorization header (default: "Bearer")
     * @param string $headerName The header name that contains the token (default: "Authorization")
     * @param string $algorithm The JWT algorithm to use (default: "HS256")
     * @param callable|null $tokenRevokedCallback Optional callback to check if token is revoked
     */
    public function __construct(
        string   $jwtKey,
        string   $tokenPrefix = 'Bearer',
        string   $headerName = 'Authorization',
        string   $algorithm = 'HS256',
        callable $tokenRevokedCallback = null
    )
    {
        $this->jwtKey = $jwtKey;
        $this->tokenPrefix = $tokenPrefix;
        $this->headerName = $headerName;
        $this->algorithm = $algorithm;
        $this->tokenRevokedCallback = $tokenRevokedCallback;
    }

    /**
     * Authenticate a request using JWT token
     *
     * @param ServerRequestInterface $request
     * @return IdentityInterface|null
     */
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        $token = $this->extractToken($request);

        if (empty($token)) {
            return null;
        }

        try {
            // Verify and decode token
            $decoded = JWT::decode($token, new Key($this->jwtKey, $this->algorithm));

            // Convert to array for easier access
            $payload = (array)$decoded;

            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Check if token has been revoked (if callback provided)
            if ($this->tokenRevokedCallback !== null &&
                call_user_func($this->tokenRevokedCallback, $token)) {
                return null;
            }

            // Extract identity information
            $id = $payload['sub'] ?? null;
            if ($id === null) {
                return null;
            }

            // Create identity with required information
            return new Identity(
                $id,
                $payload['roles'] ?? [],
                [
                    'id' => $id,
                    'email' => $payload['email'] ?? null,
                    'exp' => $payload['exp'] ?? null,
                    'iat' => $payload['iat'] ?? null,
                    // Add any additional fields you need
                ]
            );
        } catch (\Exception $e) {
            // Any JWT exception means invalid token
            return null;
        }
    }

    /**
     * Extract JWT token from request headers
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        $headerLine = $request->getHeaderLine($this->headerName);

        if (empty($headerLine)) {
            return null;
        }

        // Check if the header starts with the expected prefix
        if (strpos($headerLine, $this->tokenPrefix . ' ') === 0) {
            return substr($headerLine, strlen($this->tokenPrefix) + 1);
        }

        return null;
    }

    /**
     * Generate JWT token for a user
     *
     * @param array $userData User data to include in the token
     * @param int $expiresIn Expiration time in seconds from now
     * @return string Generated JWT token
     */
    public function generateToken(array $userData, int $expiresIn = 3600): string
    {
        if (!isset($userData['id'])) {
            throw new RuntimeException('User ID is required for token generation');
        }

        $now = time();

        // Create token payload
        $payload = [
            'iss' => 'ody-framework', // Issuer
            'aud' => 'api',           // Audience
            'sub' => $userData['id'], // Subject (user ID)
            'iat' => $now,            // Issued At
            'exp' => $now + $expiresIn, // Expiration
        ];

        // Add additional user data
        if (isset($userData['email'])) {
            $payload['email'] = $userData['email'];
        }

        if (isset($userData['roles'])) {
            $payload['roles'] = $userData['roles'];
        }

        // Generate JWT
        return JWT::encode($payload, $this->jwtKey, $this->algorithm);
    }

    /**
     * Generate refresh token
     *
     * @param string|int $userId User ID for the refresh token
     * @param int $expiresIn Expiration time in seconds from now
     * @return string Generated refresh token
     */
    public function generateRefreshToken($userId, int $expiresIn = 2592000): string
    {
        $now = time();

        // Create refresh token payload
        $payload = [
            'iss' => 'ody-framework',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)) // Unique identifier for this refresh token
        ];

        // Generate JWT for refresh token
        return JWT::encode($payload, $this->jwtKey, $this->algorithm);
    }

    /**
     * Validate and decode a refresh token
     *
     * @param string $refreshToken The refresh token to validate
     * @return array|null Decoded token payload or null if invalid
     */
    public function validateRefreshToken(string $refreshToken): ?array
    {
        try {
            $decoded = JWT::decode($refreshToken, new Key($this->jwtKey, $this->algorithm));
            $payload = (array)$decoded;

            // Ensure this is a refresh token
            if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
                return null;
            }

            // Check if expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }
}