<?php

namespace Ody\Auth\Handlers;

use Ody\Auth\AuthManager;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LogoutHandler implements RequestHandlerInterface
{
    public function __construct(
        protected AuthManager $authManager
    )
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);

        $this->authManager->logout($token);

        return new JsonResponse([
            'message' => 'Logged out successfully'
        ]);
    }
}