<?php

namespace Ody\Auth;

use Psr\Http\Message\ServerRequestInterface;

interface AdapterInterface
{
    /**
     * Authenticate the user based on the provided request.
     *
     * @param ServerRequestInterface $request
     * @return IdentityInterface|null
     */
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface;
}