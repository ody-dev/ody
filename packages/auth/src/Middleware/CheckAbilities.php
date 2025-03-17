<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\Exceptions\AuthenticationException;
use Ody\Auth\Exceptions\MissingAbilityException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckAbilities implements MiddlewareInterface
{
    /**
     * The abilities to check.
     *
     * @var array
     */
    protected $abilities = [];

    /**
     * Create a new middleware instance.
     *
     * @param  array  $abilities
     * @return void
     */
    public function __construct(array $abilities)
    {
        $this->abilities = $abilities;
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws AuthenticationException|MissingAbilityException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user || !$user->currentAccessToken()) {
            throw new AuthenticationException;
        }

        foreach ($this->abilities as $ability) {
            if (!$user->tokenCan($ability)) {
                throw new MissingAbilityException($ability);
            }
        }

        return $handler->handle($request);
    }
}