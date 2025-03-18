<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    protected AuthManager $authManager;
    protected LoggerInterface $logger;

    public function __construct(AuthManager $authManager, ?LoggerInterface $logger = null)
    {
        $this->authManager = $authManager;
        $this->logger = $logger ?? app(LoggerInterface::class);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        logger()->info('test');
        // Extract token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            $this->logger->warning('Auth failed: No Bearer token provided');
            return $this->respondUnauthorized();
        }

        $token = substr($authHeader, 7);

        // Validate token
        $tokenData = $this->authManager->validateToken($token);

        if (!$tokenData) {
            $this->logger->warning('Auth failed: Invalid token');
            return $this->respondUnauthorized();
        }

        // Attach user data to request
        $userId = $tokenData['sub'] ?? null;
        if ($userId) {
            $user = $this->authManager->getUser($userId);
            if ($user) {
                $request = $request->withAttribute('user', $user);
            }
        }

        // Continue with the request
        return $handler->handle($request);
    }

    protected function respondUnauthorized(): ResponseInterface
    {
        $response = new \Ody\Foundation\Http\Response();
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withJson([
                'error' => 'Unauthorized'
            ]);
    }
}