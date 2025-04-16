<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\Foundation;

use Mockery\Exception;
use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use Ody\Foundation\Http\ControllerDispatcher;
use Ody\Foundation\Http\ControllerPool;
use Ody\Foundation\Http\ControllerResolver;
use Ody\Foundation\Http\Request;
use Ody\Foundation\Http\Response;
use Ody\Foundation\Http\ResponseEmitter;
use Ody\Foundation\Providers\ApplicationServiceProvider;
use Ody\Foundation\Providers\ConfigServiceProvider;
use Ody\Foundation\Providers\EnvServiceProvider;
use Ody\Foundation\Providers\LoggingServiceProvider;
use Ody\Foundation\Providers\ServiceProviderManager;
use Ody\Foundation\Router\Router;
use Ody\Logger\StreamLogger;
use Ody\Middleware\MiddlewareManager;
use Ody\Middleware\MiddlewarePipeline;
use Ody\Swoole\Coroutine\ContextManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Main application class with integrated bootstrapping
 */
class Application implements RequestHandlerInterface
{
    /**
     * @var Router|null
     */
    private ?Router $router = null;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ResponseEmitter|null
     */
    private ?ResponseEmitter $responseEmitter = null;

    /**
     * @var MiddlewareManager|null
     */
    private ?MiddlewareManager $middlewareManager = null;

    /**
     * @var bool Whether the application has been bootstrapped
     */
    private bool $bootstrapped = false;

    /**
     * @var ControllerDispatcher|null
     */
    protected ?ControllerDispatcher $controllerDispatcher = null;

    /**
     * Core providers that must be registered in a specific order
     *
     * @var array|string[]
     */
    private array $providers = [
        EnvServiceProvider::class,
        ConfigServiceProvider::class,
        LoggingServiceProvider::class,
        ApplicationServiceProvider::class,
    ];

    /**
     * Application constructor with reduced dependencies
     *
     * @param Container $container
     * @param ServiceProviderManager $providerManager
     *
     * TODO: Use DI for logger & config
     */
    public function __construct(
        private readonly Container              $container,
        private readonly ServiceProviderManager $providerManager
    )
    {
        $this->logger = new StreamLogger('php://stdout');

        // Register self in container
        $this->container->instance(Application::class, $this);
        $this->container->alias(Application::class, 'app');
    }

    /**
     * Get logger
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Run the application
     *
     * @return void
     * @throws BindingResolutionException|Throwable
     */
    public function run(): void
    {
        // Ensure application is bootstrapped
        if (!$this->bootstrapped) {
            $this->bootstrap();
        }

        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $this->getResponseEmitter()->emit($response);

        // Run terminating middleware after the response has been sent
        $this->getMiddlewareManager()->terminate($request, $response);
    }

    /**
     * Check if the application has been bootstrapped
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    /**
     * Bootstrap the application by loading providers
     *
     * @return self
     * @throws Throwable
     */
    public function bootstrap(): self
    {
        if ($this->bootstrapped) {
            $this->logger->debug("Application::bootstrap() already bootstrapped, skipping");
            return $this;
        }

        $this->registerCoreProviders();
        $this->providerManager->registerConfigProviders('app.providers.http');

        $this->providerManager->boot();

        $this->configureControllerCaching();

        $this->precacheControllers();

        $this->bootstrapped = true;
        return $this;
    }

    /**
     * Configure controller caching based on application configuration
     */
    protected function configureControllerCaching(): void
    {
        $config = $this->container->make('config');
        $enableCaching = $config->get('app.controller_cache.enabled', true);
        $excludedControllers = $config->get('app.controller_cache.excluded', []);
        $controllerPool = $this->container->get(ControllerPool::class);

        ($enableCaching) ?
            $controllerPool->enableCaching() :
            $controllerPool->disableCaching();

        // Register excluded controllers
        if (!empty($excludedControllers)) {
            $controllerPool->excludeControllers($excludedControllers);
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws BindingResolutionException
     */
    public function precacheControllers(): void
    {
        // Get configuration directly if needed, or assume pool is configured
        $config = $this->container->get('config');
        $enableCaching = $config->get('app.controller_cache.enabled', true);

        if (!$enableCaching) {
            $this->logger->debug("Controller precaching skipped (caching disabled via config)");
            return;
        }

        /** @var ControllerPool $controllerPool */
        $controllerPool = $this->container->get(ControllerPool::class); // Use get() or make()

        $router = $this->container->make(Router::class);
        $routes = $router->getRoutes();

        foreach ($routes as $route) {
            $handler = $route[2]; // The handler

            if (is_string($handler) && str_contains($handler, '@')) {
                list($class, $method) = explode('@', $handler, 2);
                try {
                    if ($controllerPool->controllerIsCached($class)) {
                        continue;
                    }

                    $controllerPool->get($class);
                    $workerId = getmypid();
                    $this->logger->debug("[Worker {$workerId}] Precaching controller: {$class}");
                } catch (Throwable $e) {
                    $this->logger->error("Failed to precache controller {$class}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Register core framework service providers
     *
     * @return void
     * @throws Throwable
     */
    protected function registerCoreProviders(): void
    {
        foreach ($this->providers as $provider) {
            // Only register if class exists (allows for optional components)
            if (class_exists($provider)) {
                $this->providerManager->register($provider);
            }
        }
    }

    /**
     * Handle a request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws BindingResolutionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Add the request to the container
            $this->container->instance(ServerRequestInterface::class, $request);

            // Match the route using the Router from the container
            $router = $this->getRouter();
            $routeInfo = $router->match($request->getMethod(), $request->getUri()->getPath());

            // Handle route not found
            if ($routeInfo['status'] === 'not_found') {
                return $this->handleNotFound($request);
            }

            // Handle method not allowed
            if ($routeInfo['status'] === 'method_not_allowed') {
                return $this->handleMethodNotAllowed($request, $routeInfo['allowed_methods'] ?? []);
            }

            // If we found a route, get the handler
            $handlerIdentifier = $routeInfo['handler'];
            $routeParams = $routeInfo['vars'] ?? [];
            $controllerClass = $routeInfo['controller'] ?? null;
            $action = $routeInfo['action'] ?? null;
            $isPsr15Handler = $routeInfo['is_psr15'] ?? false;

            // Add route parameters to the request
            foreach ($routeParams as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

            ContextManager::set('_controller', $routeInfo['controller']);
            ContextManager::set('_action', $routeInfo['action']);

            if ($isPsr15Handler && $controllerClass) {
                // Resolve the PSR-15 handler instance
                $handlerInstance = $this->getControllerResolver()->createController($controllerClass);

                if (!($handlerInstance instanceof RequestHandlerInterface)) {
                    // This should ideally not happen if router check is correct, but good safeguard
                    throw new \RuntimeException("Resolved handler {$controllerClass} does not implement RequestHandlerInterface");
                }

                // Dispatch using the middleware pipeline, passing the RESOLVED INSTANCE
                return $this->dispatchWithMiddleware($request, $handlerInstance, $routeParams);
            } elseif ($controllerClass && $action) {
                return $this->dispatchToController($request, $controllerClass, $action, $routeParams);
            } elseif (is_callable($handlerIdentifier)) {
                // Pass the closure to be wrapped by the adapter
                return $this->dispatchWithMiddleware($request, $handlerIdentifier, $routeParams);

            } else {
                // Handle cases where the handler string was invalid
                $this->logger->error("Application::handle: Invalid route handler configuration detected for path.", ['routeInfo' => $routeInfo]);
                return $this->handleNotFound($request); // Or a 500 error
            }

            throw new Exception('Invalid route handler identifier');

        } catch (Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Dispatch a request to a controller
     *
     * @param ServerRequestInterface $request
     * @param string $controller
     * @param string $action
     * @param array $routeParams
     * @return ResponseInterface
     * @throws Throwable
     */
    protected function dispatchToController(
        ServerRequestInterface $request,
        string $controller,
        string $action,
        array $routeParams
    ): ResponseInterface {
        // Get or create the controller dispatcher
        $dispatcher = $this->getControllerDispatcher();

        // Dispatch the request to the controller
        return $dispatcher->dispatch($request, $controller, $action, $routeParams);
    }

    /**
     * Dispatch a request with middleware
     *
     * @param ServerRequestInterface $request
     * @param callable|RequestHandlerInterface $handler
     * @param array $routeParams
     * @return ResponseInterface
     * @throws BindingResolutionException
     */
    protected function dispatchWithMiddleware(
        ServerRequestInterface           $request,
        callable|RequestHandlerInterface $handler,
        array                            $routeParams
    ): ResponseInterface {
        $middlewareManager = $this->getMiddlewareManager();
        $controllerClass = null;
        $action = null;

        if (is_object($handler) && $handler instanceof PsrRequestHandlerInterface) {
            $controllerClass = get_class($handler);
        }

        $middlewareStack = $middlewareManager->getMiddlewareForRoute(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $controllerClass
        );

        $finalHandlerCallable = null;
        if ($handler instanceof RequestHandlerInterface) {
            $finalHandlerCallable = function (ServerRequestInterface $request) use ($handler) {
                return $handler->handle($request);
            };
        } elseif (is_callable($handler)) {
            // If it's a callable (Closure), create a wrapper
            $finalHandlerCallable = function (ServerRequestInterface $request) use ($handler, $routeParams) {
                $response = $this->container->make(Response::class); // Provide a response instance
                // Add route parameters JUST before calling the closure handler
                // (Redundant if already done in Application::handle, but safe)
                foreach ($routeParams as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                return call_user_func($handler, $request, $response, $routeParams);
            };
        } else {
            throw new \InvalidArgumentException("Invalid handler type passed to dispatchWithMiddleware");
        }

        $pipeline = new MiddlewarePipeline(
            new Http\CallableHandlerAdapter($finalHandlerCallable)
        );

        foreach ($middlewareStack as $middleware) {
            try {
                $instance = $middlewareManager->resolve($middleware);
                $pipeline->add($instance);
            } catch (Throwable $e) {
                $this->container->make(LoggerInterface::class)->error('Error resolving/adding middleware in dispatchWithMiddleware', [
                    'middleware' => is_string($middleware) ? $middleware : (is_object($middleware) ? get_class($middleware) : 'Unknown type'),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $pipeline->handle($request);
    }

    /**
     * Get the controller dispatcher
     *
     * @return ControllerDispatcher
     * @throws BindingResolutionException
     */
    protected function getControllerDispatcher(): ControllerDispatcher
    {
        if (!$this->controllerDispatcher) {
            $this->controllerDispatcher = new ControllerDispatcher(
                $this->container,
                new ControllerResolver(
                    $this->container->make('logger'),
                    $this->container->make(ControllerPool::class)
                ),
                $this->container->make('logger'),
                $this->getMiddlewareManager(),
            );
        }

        return $this->controllerDispatcher;
    }

    /**
     * Get the middleware manager
     *
     * @return MiddlewareManager
     * @throws BindingResolutionException
     */
    public function getMiddlewareManager(): MiddlewareManager
    {
        if ($this->middlewareManager === null && $this->container->has(MiddlewareManager::class)) {
            $this->middlewareManager = $this->container->make(MiddlewareManager::class);
        }

        return $this->middlewareManager;
    }

    /**
     * Handle a route not found error
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function handleNotFound(ServerRequestInterface $request): ResponseInterface
    {
        return (new Response())
            ->status(404)
            ->json([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'path' => $request->getUri()->getPath()
            ]);
    }

    /**
     * Handle a method not allowed error
     *
     * @param ServerRequestInterface $request
     * @param array $allowedMethods
     * @return ResponseInterface
     */
    protected function handleMethodNotAllowed(ServerRequestInterface $request, array $allowedMethods): ResponseInterface
    {
        return (new Response())
            ->status(405)
            ->withHeader('Allow', implode(', ', $allowedMethods))
            ->json([
                'error' => 'Method Not Allowed',
                'message' => 'The requested method is not allowed for this resource',
                'allowed_methods' => $allowedMethods
            ]);
    }

    /**
     * Handle an exception
     *
     * @param ServerRequestInterface $request
     * @param Throwable $e
     * @return ResponseInterface
     * @throws BindingResolutionException
     */
    protected function handleException(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        // Log the exception
        $this->container->make('logger')->error('Application Exception: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Use the exception handler if available
        if ($this->container->has('error.handler')) {
            $handler = $this->container->make('error.handler');
            return $handler->render($request, $e);
        }

        // Default error response
        $debug = env('APP_DEBUG', false);

        $errorData = [
            'error' => 'Server Error',
            'message' => $debug ? $e->getMessage() : 'An error occurred while processing your request'
        ];

        if ($debug) {
            $errorData['exception'] = get_class($e);
            $errorData['file'] = $e->getFile();
            $errorData['line'] = $e->getLine();
            $errorData['trace'] = explode("\n", $e->getTraceAsString());
        }

        return (new Response())
            ->status(500)
            ->json($errorData);
    }

    /**
     * Get router instance from the container
     *
     * @return Router
     * @throws BindingResolutionException
     */
    public function getRouter(): Router
    {
        if ($this->router === null) {
            $this->router = $this->container->make(Router::class);
        }

        return $this->router;
    }

    /**
     * Get response emitter (lazy-loaded)
     *
     * @return ResponseEmitter
     * @throws BindingResolutionException
     */
    public function getResponseEmitter(): ResponseEmitter
    {
        if ($this->responseEmitter === null) {
            $this->responseEmitter = new ResponseEmitter(
                $this->container->make(LoggerInterface::class),
                true,
                8192
            );
        }

        return $this->responseEmitter;
    }

    /**
     * Get the controller/handler resolver
     * @throws BindingResolutionException
     */
    protected function getControllerResolver(): ControllerResolver
    {
        if ($this->container->has(ControllerResolver::class)) {
            return $this->container->make(ControllerResolver::class);
        }

        // Fallback creation (ensure dependencies like LoggerInterface, ControllerPool are available)
        return new ControllerResolver(
            $this->container->make(LoggerInterface::class),
            $this->container->make(ControllerPool::class)
        );
    }
}