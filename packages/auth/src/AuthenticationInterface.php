<?php

namespace Ody\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface AuthenticationInterface
{
    /**
     * Authenticate the user based on the provided request.
     *
     * @param ServerRequestInterface $request
     * @return IdentityInterface|null
     */
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface;

    /**
     * Return an unauthorized response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface;
}