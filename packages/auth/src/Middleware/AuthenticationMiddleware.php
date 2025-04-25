<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthenticationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthenticationMiddleware implements MiddlewareInterface
{
    public const string IDENTITY_ATTRIBUTE = 'identity';

    private AuthenticationInterface $authentication;

    public function __construct(AuthenticationInterface $authentication)
    {
        $this->authentication = $authentication;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->authentication->authenticate($request);

        if (null === $identity) {
            return $this->authentication->unauthorizedResponse($request);
        }

        // Add identity to request attributes and continue
        return $handler->handle($request->withAttribute(self::IDENTITY_ATTRIBUTE, $identity));
    }
}