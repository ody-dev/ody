<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AttachUserToRequest implements MiddlewareInterface
{
    /**
     * The authentication manager instance.
     *
     * @var \Ody\Auth\AuthManager
     */
    protected $auth;

    /**
     * The guards that should be used to authenticate.
     *
     * @var array
     */
    protected $guards;

    /**
     * Create a new middleware instance.
     *
     * @param  \Ody\Auth\AuthManager  $auth
     * @param  array  $guards
     * @return void
     */
    public function __construct(AuthManager $auth, array $guards = [])
    {
        $this->auth = $auth;
        $this->guards = $guards ?: [null];
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Try to authenticate the user using each guard
        $user = null;

        foreach ($this->guards as $guard) {
            if ($user = $this->auth->guard($guard)->user()) {
                break;
            }
        }

        // If we found an authenticated user, attach it to the request
        if ($user) {
            $request = $request->withAttribute('user', $user);
        }

        return $handler->handle($request);
    }
}