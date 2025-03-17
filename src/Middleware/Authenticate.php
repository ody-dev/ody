<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Ody\Auth\Exceptions\AuthenticationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Authenticate implements MiddlewareInterface
{
    /**
     * The authentication factory instance.
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
        $this->guards = $guards;
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws AuthenticationException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->authenticate($request, $this->guards);

        return $handler->handle($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Ody\Auth\Exceptions\AuthenticationException
     */
    protected function authenticate(ServerRequestInterface $request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }



        foreach ($guards as $guard) {
            var_dump($this->auth->guard($guard)->user());
            if ($this->auth->guard($guard)->user()) {
                return;
            }
        }

        throw new AuthenticationException(
            'Unauthenticated.', $guards
        );
    }
}