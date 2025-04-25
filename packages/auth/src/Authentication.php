<?php

namespace Ody\Auth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Authentication implements AuthenticationInterface
{
    private AdapterInterface $adapter;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(AdapterInterface $adapter, ResponseFactoryInterface $responseFactory)
    {
        $this->adapter = $adapter;
        $this->responseFactory = $responseFactory;
    }

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        return $this->adapter->authenticate($request);
    }

    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody([
                'unauthorized',
            ]);
    }
}