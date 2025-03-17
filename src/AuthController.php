<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Auth;

use App\Models\User;
use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class AuthController
{
    /**
     * @var AuthManager
     */
    protected $auth;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new controller instance.
     *
     * @param AuthManager $auth
     * @param LoggerInterface $logger
     */
    public function __construct(AuthManager $auth, LoggerInterface $logger)
    {
        $this->auth = $auth;
        $this->logger = $logger;
    }

    /**
     * Handle a login request to the application.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        // Validate login data
        if (empty($data['email']) || empty($data['password'])) {
            return $this->jsonResponse($response->withStatus(422), [
                'message' => 'Email and password are required',
                'errors' => [
                    'email' => empty($data['email']) ? ['Email is required'] : [],
                    'password' => empty($data['password']) ? ['Password is required'] : [],
                ]
            ]);
        }

        // Retrieve user by email
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return $this->jsonResponse($response->withStatus(401), [
                'message' => 'Invalid credentials'
            ]);
        }

        // Verify password
        $hasher = new PasswordHasher();

        if (!$hasher->check($data['password'], $user->password)) {
            return $this->jsonResponse($response->withStatus(401), [
                'message' => 'Invalid credentials'
            ]);
        }

        // Create token
        $tokenName = $data['device_name'] ?? ($request->getServerParams()['HTTP_USER_AGENT'] ?? 'Unknown Device');
        $token = $user->createToken($tokenName);

        return $this->jsonResponse($response, [
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Handle a registration request to the application.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        var_dump($request->getParsedBody());

        // Validate registration data
        $errors = [];

//        if (empty($data['name'])) {
//            $errors['name'] = ['Name is required'];
//        }
//
//        if (empty($data['email'])) {
//            $errors['email'] = ['Email is required'];
//        } elseif (User::where('email', $data['email'])->exists()) {
//            $errors['email'] = ['Email is already taken'];
//        }
//
//        if (empty($data['password'])) {
//            $errors['password'] = ['Password is required'];
//        } elseif (strlen($data['password']) < 8) {
//            $errors['password'] = ['Password must be at least 8 characters'];
//        }
//
//        if (!empty($errors)) {
//            return $this->jsonResponse($response->withStatus(422), [
//                'message' => 'Validation errors',
//                'errors' => $errors
//            ]);
//        }

        // Create user
        $hasher = new PasswordHasher();

        var_dump($data['name']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hasher->make($data['password']),
        ]);

        // Create token
        $tokenName = $data['device_name'] ?? ($request->getServerParams()['HTTP_USER_AGENT'] ?? 'Unknown Device');
        $token = $user->createToken($tokenName);

        return $this->jsonResponse($response->withStatus(201), [
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function user(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->jsonResponse($response->withStatus(401), [
                'message' => 'Unauthenticated'
            ]);
        }

        return $this->jsonResponse($response, [
            'user' => $user
        ]);
    }

    /**
     * Log the user out (invalidate the token).
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->jsonResponse($response->withStatus(401), [
                'message' => 'Unauthenticated'
            ]);
        }

        // Delete current token
        if ($token = $user->currentAccessToken()) {
            $token->delete();
        }

        return $this->jsonResponse($response, [
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Helper method to create JSON responses
     *
     * @param ResponseInterface $response
     * @param mixed $data
     * @return ResponseInterface
     */
    private function jsonResponse(ResponseInterface $response, $data): ResponseInterface
    {
        // Always set JSON content type
        $response = $response->withHeader('Content-Type', 'application/json');

        // Use Response class methods if available
        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        // For other PSR-7 implementations
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}