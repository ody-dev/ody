<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AttachUserToRequest implements MiddlewareInterface
{
    /**
     * The authentication manager instance.
     *
     * @var \Ody\Auth\AuthManager|null
     */
    protected $auth;

    /**
     * The guards that should be used to authenticate.
     *
     * @var array
     */
    protected $guards;

    /**
     * The logger instance.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Create a new middleware instance.
     *
     * @param \Ody\Auth\AuthManager|null $auth
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param  array  $guards
     * @return void
     */
    public function __construct(?AuthManager $auth = null, ?LoggerInterface $logger = null, array $guards = [])
    {
        // Handle dependency resolution via the application container if not provided
        $this->auth = $auth ?? (function_exists('app') ? app('auth') : null);
        $this->logger = $logger ?? (function_exists('app') ? app(LoggerInterface::class) : new NullLogger());
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
        // For now, just log and pass through without trying to attach a user
        $this->logger->info('AttachUserToRequest middleware processing request: ' . $request->getUri()->getPath());

        try {
            // Simply pass the request through to the next handler
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logger->error('Error in AttachUserToRequest middleware: ' . $e->getMessage(), [
                'path' => $request->getUri()->getPath(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception to be handled by error handlers
            throw $e;
        }
    }
}