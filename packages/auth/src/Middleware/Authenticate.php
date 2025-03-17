<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Ody\Foundation\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Authenticate implements MiddlewareInterface
{
    /**
     * The authentication factory instance.
     *
     * @var \Ody\Auth\AuthManager|null
     */
    protected $auth;

    /**
     * The logger instance.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The default guard to use.
     *
     * @var string|null
     */
    protected $defaultGuard;

    /**
     * Create a new middleware instance.
     *
     * @param \Ody\Auth\AuthManager|null $auth
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string|null $defaultGuard
     * @return void
     */
    public function __construct(?AuthManager $auth = null, ?LoggerInterface $logger = null, ?string $defaultGuard = null)
    {
        // Store the provided dependencies
        $this->auth = $auth;
        $this->logger = $logger ?? new NullLogger();
        $this->defaultGuard = $defaultGuard;

        // Log dependency availability
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->debug('Authenticate middleware constructed', [
                'auth_available' => $this->auth !== null,
                'default_guard' => $this->defaultGuard
            ]);
        }
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
        // Log that we've entered the authenticate middleware
        $this->logger->info('Authenticate middleware triggered');

        // Dump request information for debugging
        $this->logger->debug('Request details', [
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'attributes' => $request->getAttributes(),
            'headers' => $request->getHeaders()
        ]);

        // Handle case where auth manager wasn't injected and couldn't be resolved
        if (!$this->auth) {
            $this->logger->error('Authenticate: AuthManager not available');

            // Return unauthorized response since we can't authenticate
            return $this->createUnauthorizedResponse();
        }

        // Get the guard parameter from the request attribute or use the default
        $guard = $request->getAttribute('middleware_guard', $this->defaultGuard);

        // If multiple guards are specified (comma-separated), convert to array
        if (is_string($guard) && strpos($guard, ',') !== false) {
            $guard = explode(',', $guard);
        }

        // Use whatever guard(s) we have
        $guards = $guard ? (array)$guard : ['sanctum', 'token', 'web']; // Try all guards by default

        $this->logger->debug('Auth middleware using guards', ['guards' => $guards]);

        // Authenticate with any of the guards
        foreach ($guards as $guard) {
            try {
                if ($this->auth->guard($guard)->user()) {
                    // If authenticated, attach the user to the request for downstream middleware/controllers
                    $user = $this->auth->guard($guard)->user();
                    $request = $request->withAttribute('user', $user);

                    $this->logger->debug('User authenticated', [
                        'guard' => $guard,
                        'user_id' => $user->getAuthIdentifier()
                    ]);

                    // Return the response with the added user attribute
                    return $handler->handle($request);
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Error checking guard {$guard}: " . $e->getMessage());
                continue;
            }
        }

        // If we reach here, no guard authenticated the user
        $this->logger->warning('Authentication failed', ['guards' => $guards]);

        // Create and return an unauthorized response
        return $this->createUnauthorizedResponse();
    }

    /**
     * Create an unauthorized response.
     *
     * @return ResponseInterface
     */
    protected function createUnauthorizedResponse(): ResponseInterface
    {
        $response = new Response();
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withJson([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required for this resource'
            ]);
    }
}