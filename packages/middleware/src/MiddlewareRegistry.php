<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\Middleware;

use Ody\Container\Container;
use Ody\Middleware\Adapters\CallableMiddlewareAdapter;
use Ody\Middleware\Attributes\Middleware;
use Ody\Middleware\Attributes\MiddlewareGroup;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Throwable;

/**
 * Manages the registration, resolution, and retrieval of middleware.
 *
 * @phpstan-type MiddlewareDefinition string|callable|MiddlewareInterface|MiddlewareConfig
 * Represents a middleware definition before resolution. Can be:
 * - string: Class name, named middleware alias, or group name.
 * - callable: A closure or other callable implementing middleware logic.
 * - MiddlewareInterface: An already instantiated middleware.
 * - MiddlewareConfig: A configuration object holding class and parameters.
 *
 * @phpstan-type AttributeMiddlewareConfig array{class?: string|list<string>, group?: string, parameters: array<mixed>}
 * Represents the structure of middleware definitions extracted from attributes.
 */
class MiddlewareRegistry
{
    /**
     * @var Container The dependency injection container.
     */
    protected Container $container;

    /**
     * @var LoggerInterface The logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * Global middleware applied to all requests.
     * @var list<MiddlewareDefinition>
     */
    protected array $global = [];

    /**
     * Route-specific middleware, keyed by "METHOD:path".
     * @var array<string, list<MiddlewareDefinition>>
     */
    protected array $routes = [];

    /**
     * Named middleware map (alias => definition).
     * @var array<string, MiddlewareDefinition>
     */
    protected array $named = [];

    /**
     * Middleware groups (group_name => list of definitions).
     * @var array<string, list<MiddlewareDefinition>>
     */
    protected array $groups = [];

    /**
     * Cache of resolved middleware instances (cache_key => instance).
     * @var array<string, MiddlewareInterface>
     */
    protected array $resolved = [];

    /**
     * Cache of resolved controller middleware (class_name => attribute config list).
     * @var array<class-string, list<AttributeMiddlewareConfig>>
     */
    protected array $controllerCache = [];

    /**
     * Cache of resolved method middleware (class_name@method => attribute config list).
     * @var array<string, list<AttributeMiddlewareConfig>>
     */
    protected array $methodCache = [];

    /**
     * @var bool Whether to collect cache statistics.
     */
    protected bool $collectStats;

    /**
     * Cache hits for statistics (cache_key => hit_count).
     * @var array<string, int>
     */
    protected array $cacheHits = [];

    /**
     * Cache for Reflection objects to avoid repeated instantiation.
     * @var array<class-string, array{class: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: list<ReflectionParameter>}>
     */
    protected array $reflectionCache = [];

    /**
     * Constructor
     *
     * @param Container $container The DI container.
     * @param LoggerInterface|null $logger Optional logger. Defaults to NullLogger.
     * @param bool $collectStats Whether to collect cache statistics.
     */
    public function __construct(
        Container $container,
        ?LoggerInterface $logger = null,
        bool      $collectStats = false
    )
    {
        $this->container = $container;
        $this->logger = $logger ?? new NullLogger();
        $this->collectStats = $collectStats;
    }

    /**
     * Register middleware for a specific route.
     *
     * @param string $method HTTP method (e.g., 'GET', 'POST').
     * @param string $path Route path pattern.
     * @param MiddlewareDefinition $middleware The middleware definition to add.
     * @return $this
     */
    public function addForRoute(string $method, string $path, $middleware): self
    {
        $routeKey = $this->formatRouteKey($method, $path);
        // Ensure the key exists and is an array before appending
        if (!isset($this->routes[$routeKey]) || !is_array($this->routes[$routeKey])) {
            $this->routes[$routeKey] = [];
        }

        $this->routes[$routeKey][] = $middleware;

        return $this;
    }

    /**
     * Register a global middleware.
     *
     * @param MiddlewareDefinition $middleware The middleware definition to add globally.
     * @return $this
     */
    public function global($middleware): self
    {
        $this->global[] = $middleware;
        return $this;
    }

    /**
     * Register a named middleware alias.
     *
     * @param string $name The alias for the middleware.
     * @param MiddlewareDefinition $middleware The middleware definition.
     * @return $this
     */
    public function named(string $name, $middleware): self
    {
        $this->named[$name] = $middleware;
        return $this;
    }

    /**
     * Register a middleware group.
     *
     * @param string $name The name of the group.
     * @param list<MiddlewareDefinition> $middlewareList List of middleware definitions for the group.
     * @return $this
     */
    public function group(string $name, array $middlewareList): self
    {
        $this->groups[$name] = $middlewareList;
        return $this;
    }

    /**
     * Format a route key string.
     *
     * @param string $method HTTP method.
     * @param string $path Route path.
     * @return string Formatted route key (e.g., "GET:/users").
     */
    protected function formatRouteKey(string $method, string $path): string
    {
        return strtoupper($method) . ':' . $path;
    }

    /**
     * Build a middleware pipeline for a specific route (excluding attribute middleware).
     * Expands named middleware and groups.
     *
     * @param string $method HTTP method.
     * @param string $path Route path.
     * @return list<MiddlewareDefinition> Expanded list of middleware definitions for this route.
     */
    public function buildPipeline(string $method, string $path): array
    {
        $middlewareList = $this->global;

        $routeKey = $this->formatRouteKey($method, $path);
        if (isset($this->routes[$routeKey])) {
            $routeMiddleware = is_array($this->routes[$routeKey]) ? $this->routes[$routeKey] : [$this->routes[$routeKey]];
            $middlewareList = array_merge($middlewareList, $routeMiddleware);
        }

        // Process and expand the middleware list
        return $this->expandMiddleware($middlewareList);
    }

    /**
     * Get all middleware references (names/classes/configs) for a route, including controller/method attributes.
     *
     * @param string $method HTTP method.
     * @param string $path Route path.
     * @param object|class-string|null $controller Controller class name or instance.
     * @param string|null $action Controller method name.
     * @return list<MiddlewareDefinition> Combined list of middleware definitions (strings, configs, etc.).
     */
    public function getMiddlewareForRoute(
        string        $method,
        string        $path,
        object|string $controller = null,
        ?string       $action = null
    ): array
    {
        $routeDefinedMiddleware = $this->buildPipeline($method, $path);

        $attributeMiddlewareDefinitions = [];
        if ($controller) {
            $attributeMiddlewareConfigs = $this->getMiddleware($controller, $action);
            $attributeMiddlewareDefinitions = $this->convertAttributeConfigsToDefinitions($attributeMiddlewareConfigs);
        }

        $combinedList = array_merge(
            $routeDefinedMiddleware,
            $this->expandMiddleware($attributeMiddlewareDefinitions)
        );

        $finalExpandedList = $this->expandMiddleware($combinedList);

        $uniqueMiddleware = array_map('unserialize', array_unique(array_map('serialize', $finalExpandedList)));

        return array_values($uniqueMiddleware);
    }

    /**
     * Helper to convert AttributeMiddlewareConfig[] to MiddlewareDefinition[]
     * @param list<AttributeMiddlewareConfig> $configs
     * @return list<MiddlewareDefinition>
     */
    protected function convertAttributeConfigsToDefinitions(array $configs): array
    {
        // ... (implementation from previous answer) ...
        $definitions = [];
        foreach ($configs as $config) {
            if (isset($config['class']) && is_string($config['class'])) {
                if (!empty($config['parameters']) && is_array($config['parameters'])) {
                    $definitions[] = new MiddlewareConfig($config['class'], $config['parameters']);
                } else {
                    $definitions[] = $config['class'];
                }
            } elseif (isset($config['class']) && is_array($config['class'])) {
                $parameters = $config['parameters'] ?? [];
                foreach ($config['class'] as $class) {
                    if (is_string($class)) {
                        if (!empty($parameters) && is_array($parameters)) {
                            $definitions[] = new MiddlewareConfig($class, $parameters);
                        } else {
                            $definitions[] = $class;
                        }
                    }
                }
            } elseif (isset($config['group']) && is_string($config['group'])) {
                $definitions[] = $config['group'];
            }
        }
        return $definitions;
    }

    /**
     * Convert attribute middleware configuration arrays into a flat list of middleware references (strings).
     *
     * @param list<AttributeMiddlewareConfig> $attributeMiddlewareConfigs Middleware definitions from attributes.
     * @return list<string> List of middleware class names or group names.
     */
    protected function convertAttributeFormat(array $attributeMiddlewareConfigs): array
    {
        $result = [];

        foreach ($attributeMiddlewareConfigs as $middleware) {
            // Check if 'class' key exists and holds a string (single class)
            if (isset($middleware['class']) && is_string($middleware['class'])) {
                $result[] = $middleware['class'];
            } // Check if 'class' key exists and holds a list of strings
            elseif (isset($middleware['class']) && is_array($middleware['class'])) {
                foreach ($middleware['class'] as $class) {
                    if (is_string($class)) { // Ensure it's a string
                        $result[] = $class;
                    }
                }
            } // Check if 'group' key exists (holds group name string)
            elseif (isset($middleware['group']) && is_string($middleware['group'])) {
                $result[] = $middleware['group'];
            }
            // Note: Parameters are ignored here as we only extract the reference string
        }

        // Ensure unique values and re-index to maintain list<string> type
        return array_values(array_unique($result));
    }


    /**
     * Expand middleware references (resolve named middleware and groups recursively).
     *
     * @param list<MiddlewareDefinition> $middleware List of middleware definitions (can contain names/groups).
     * @return list<MiddlewareDefinition> Expanded list containing only concrete definitions (class strings, callables, instances, configs).
     */
    protected function expandMiddleware(array $middleware): array
    {
        $result = [];

        foreach ($middleware as $item) {
            if (is_string($item)) {
                // Check if it's a named middleware alias
                if (isset($this->named[$item])) {
                    // Add the definition associated with the name
                    $result[] = $this->named[$item];
                    continue;
                }

                // Check if it's a middleware group name
                if (isset($this->groups[$item]) && is_array($this->groups[$item])) {
                    // Recursively expand the group and merge results
                    $expanded = $this->expandMiddleware($this->groups[$item]);
                    $result = array_merge($result, $expanded);
                    continue;
                }
            }

            // If it's not a resolvable string (name/group), add it as is
            // This includes class strings, MiddlewareConfig, callables, instances
            $result[] = $item;
        }

        // Return a list (re-indexed sequential integer keys)
        return array_values($result);
    }


    /**
     * Resolve a middleware definition to an instance of MiddlewareInterface.
     * Handles caching.
     *
     * @param MiddlewareDefinition $middleware The definition to resolve.
     * @return MiddlewareInterface The resolved middleware instance.
     * @throws RuntimeException If middleware cannot be resolved.
     */
    public function resolve($middleware): MiddlewareInterface
    {
        // If already an instance, return it directly.
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // Generate a cache key based on the definition.
        $cacheKey = $this->getCacheKey($middleware);

        // Check cache first.
        if (isset($this->resolved[$cacheKey])) {
            if ($this->collectStats) {
                $this->cacheHits[$cacheKey] = ($this->cacheHits[$cacheKey] ?? 0) + 1;
            }
            return $this->resolved[$cacheKey];
        }

        try {
            // Resolve the middleware definition to an instance.
            $instance = $this->resolveMiddleware($middleware);

            // Cache the resolved instance.
            $this->resolved[$cacheKey] = $instance;

            return $instance;
        } catch (Throwable $e) { // Catch any throwable for robust error handling.
            $middlewareIdentifier = is_string($middleware) ? $middleware : (is_object($middleware) ? get_class($middleware) : gettype($middleware));
            $this->logger->error('Failed to resolve middleware', [
                'middleware' => $middlewareIdentifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Optional: include trace for debugging
            ]);

            // Re-throw as a RuntimeException for consistent error handling upstream.
            throw new RuntimeException(
                "Failed to resolve middleware '{$middlewareIdentifier}': " . $e->getMessage(),
                (int)$e->getCode(), // Preserve original code if any
                $e // Preserve original exception for context
            );
        }
    }

    /**
     * Get a unique cache key for a middleware definition.
     *
     * @param MiddlewareDefinition $middleware The middleware definition.
     * @return string The generated cache key.
     */
    protected function getCacheKey($middleware): string
    {
        if (is_string($middleware)) {
            // Prefix helps distinguish types if hashes collide (unlikely but possible)
            return 'str:' . $middleware;
        }

        // Handle standard callable array format [class, method] or [object, method]
        if (is_array($middleware) && count($middleware) === 2 && isset($middleware[0], $middleware[1]) && is_string($middleware[1])) {
            if (is_object($middleware[0])) {
                return 'call_obj:' . get_class($middleware[0]) . '::' . $middleware[1];
            } elseif (is_string($middleware[0]) && (class_exists($middleware[0]) || interface_exists($middleware[0]))) { // Check if it looks like a class string
                return 'call_static:' . $middleware[0] . '::' . $middleware[1];
            }
            // Fallthrough for other array types
        }

        // Handle MiddlewareConfig specifically
        if ($middleware instanceof MiddlewareConfig) {
            // Include class and serialized parameters for uniqueness
            return 'config:' . $middleware->getClass() . ':' . md5(serialize($middleware->getParameters()));
        }

        // Handle other objects (likely closures or invokable objects)
        if (is_object($middleware)) {
            // spl_object_hash is unique per instance *within a request*
            return 'obj:' . get_class($middleware) . ':' . spl_object_hash($middleware);
        }

        // Handle other array types (less common for middleware)
        if (is_array($middleware)) {
            // Serialize for a somewhat stable hash, though order matters.
            return 'arr:' . md5(serialize($middleware));
        }

        // Fallback for other types (resource, null, etc. - should not happen for valid middleware)
        return 'other:' . gettype($middleware) . ':' . md5(serialize($middleware));
    }


    /**
     * Resolve middleware from various definition formats into a MiddlewareInterface instance.
     *
     * @param MiddlewareDefinition $middleware The middleware definition.
     * @return MiddlewareInterface The resolved middleware instance.
     * @throws RuntimeException If middleware cannot be resolved or is invalid.
     */
    protected function resolveMiddleware($middleware): MiddlewareInterface
    {
        // Handle MiddlewareConfig objects: Instantiate with parameters.
        if ($middleware instanceof MiddlewareConfig) {
            $class = $middleware->getClass();
            $parameters = $middleware->getParameters();

            try {
                $instance = $this->createInstanceWithParameters($class, $parameters);
            } catch (ReflectionException $e) {
                throw new RuntimeException("Reflection error creating instance of '$class': " . $e->getMessage(), 0, $e);
            } catch (Throwable $e) {
                throw new RuntimeException("Error creating instance of '$class': " . $e->getMessage(), 0, $e);
            }


            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
            // If the created instance is callable but not MiddlewareInterface, wrap it.
            if (is_callable($instance)) {
                $this->logger->debug("Resolved '$class' via MiddlewareConfig resulted in a callable, wrapping with adapter.");
                return new CallableMiddlewareAdapter($instance);
            }

            throw new RuntimeException(
                "Middleware class '$class' resolved via MiddlewareConfig must implement MiddlewareInterface or be callable."
            );
        }

        // Handle string class names: Resolve via container or reflection.
        if (is_string($middleware) && class_exists($middleware)) {
            $instance = null;
            try {
                // Prefer container resolution for dependency injection.
                if ($this->container->has($middleware)) {
                    $instance = $this->container->make($middleware);
                } else {
                    // Fallback to reflection-based instantiation if not in container.
                    $this->logger->debug("Middleware class '$middleware' not found in container, attempting reflection instantiation.");
                    $instance = $this->createInstanceWithReflection($middleware);
                }
            } catch (ReflectionException $e) {
                throw new RuntimeException("Reflection error resolving middleware class '$middleware': " . $e->getMessage(), 0, $e);
            } catch (Throwable $e) {
                // Catch potential container errors or other instantiation issues.
                throw new RuntimeException("Error resolving middleware class '$middleware': " . $e->getMessage(), 0, $e);
            }

            // Ensure the resolved instance is valid middleware.
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
            // If it's callable, wrap it.
            if (is_callable($instance)) {
                $this->logger->debug("Resolved class '$middleware' resulted in a callable, wrapping with adapter.");
                return new CallableMiddlewareAdapter($instance);
            }

            throw new RuntimeException(
                "Middleware class '$middleware' must resolve to an instance of MiddlewareInterface or a callable."
            );
        }

        // Handle direct callables (Closures, invokable objects not matching other types).
        if (is_callable($middleware)) {
            return new CallableMiddlewareAdapter($middleware);
        }

        // If none of the above match, the definition is invalid.
        $type = is_object($middleware) ? get_class($middleware) : gettype($middleware);
        throw new RuntimeException(
            "Invalid middleware definition provided. Expected class name, MiddlewareInterface instance, callable, or MiddlewareConfig. Got: " . $type
        );
    }

    /**
     * Create an instance of a class, attempting to use provided parameters
     * and falling back to container/reflection for remaining dependencies.
     *
     * @template T of object
     * @param class-string<T> $className The class to instantiate.
     * @param array<string, mixed> $parameters Parameters to pass to the constructor (name => value).
     * @return T The created instance.
     * @throws ReflectionException If reflection fails.
     * @throws RuntimeException If a parameter cannot be resolved.
     */
    protected function createInstanceWithParameters(string $className, array $parameters): object
    {
        // Use reflection cache
        if (!isset($this->reflectionCache[$className])) {
            $reflectionClass = new ReflectionClass($className);
            if (!$reflectionClass->isInstantiable()) {
                throw new RuntimeException("Class $className is not instantiable");
            }
            $constructor = $reflectionClass->getConstructor();
            $this->reflectionCache[$className] = [
                'class' => $reflectionClass,
                'constructor' => $constructor,
                'parameters' => $constructor ? $constructor->getParameters() : []
            ];
        }

        /** @var ReflectionClass<T> $reflectionClass */
        $reflectionClass = $this->reflectionCache[$className]['class'];
        $constructor = $this->reflectionCache[$className]['constructor'];
        $constructorParams = $this->reflectionCache[$className]['parameters'];

        // If no constructor, create instance directly.
        if ($constructor === null) {
            return $reflectionClass->newInstanceWithoutConstructor(); // More direct if no constructor
        }

        $resolvedParams = [];
        foreach ($constructorParams as $param) {
            $paramName = $param->getName();

            // 1. Use explicitly provided parameter if available.
            if (array_key_exists($paramName, $parameters)) {
                $resolvedParams[] = $parameters[$paramName];
                continue;
            }

            // 2. Try resolving by type hint from the container.
            $paramType = $param->getType();
            if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                $typeName = $paramType->getName();
                if ($this->container->has($typeName)) {
                    try {
                        $resolvedParams[] = $this->container->make($typeName);
                        continue;
                    } catch (Throwable $e) {
                        // Log container resolution failure, but proceed to check default/optional
                        $this->logger->debug("Container failed to resolve type '{$typeName}' for parameter '{$paramName}' in '{$className}': " . $e->getMessage());
                    }
                }
                // Special case for LoggerInterface if not directly bound by its class name
                if ($typeName === LoggerInterface::class && $this->container->has(LoggerInterface::class)) { // Check interface directly
                    $resolvedParams[] = $this->container->make(LoggerInterface::class);
                    continue;
                }
            }

            // 3. Use default value if the parameter is optional.
            if ($param->isOptional()) {
                try {
                    $resolvedParams[] = $param->getDefaultValue();
                    continue;
                } catch (ReflectionException $e) {
                    // This can happen for e.g. internal constants in default values
                    throw new RuntimeException("Could not get default value for optional parameter '{$paramName}' in class {$className}: " . $e->getMessage(), 0, $e);
                }
            }

            // 4. If nullable, allow null.
            if ($param->allowsNull()) {
                $resolvedParams[] = null;
                continue;
            }

            // Cannot resolve parameter - this is an error.
            throw new RuntimeException("Cannot resolve constructor parameter '{$paramName}' for class {$className}");
        }

        // Create instance with resolved parameters.
        return $reflectionClass->newInstanceArgs($resolvedParams);
    }

    /**
     * Clear reflection caches to potentially free memory, e.g., in long-running processes.
     *
     * @return void
     */
    public function clearReflectionCache(): void
    {
        $this->reflectionCache = [];
        $this->logger->debug("Reflection cache cleared.");
    }


    /**
     * Create an instance using reflection, resolving constructor dependencies solely via the container or defaults.
     *
     * @template T of object
     * @param class-string<T> $className The class to instantiate.
     * @return T The created instance.
     * @throws ReflectionException If reflection fails.
     * @throws RuntimeException If a non-optional parameter cannot be resolved.
     */
    protected function createInstanceWithReflection(string $className): object
    {
        // Use shared method logic but with empty parameters array
        return $this->createInstanceWithParameters($className, []);
    }

    /**
     * Get combined middleware configurations from class and method attributes.
     *
     * @param object|class-string $controller Controller class name or instance.
     * @param string $method Method name.
     * @return list<AttributeMiddlewareConfig> Combined list of middleware configurations from attributes.
     */
    public function getMiddleware(object|string $controller, ?string $method): array // <-- Allow null $method
    {
        $controllerClass = is_object($controller) ? get_class($controller) : $controller;
        // Ensure class exists for reflection/cache key
        if (!class_exists($controllerClass) && !interface_exists($controllerClass)) {
            $this->logger->warning("Attempted to get middleware for non-existent class: {$controllerClass}");
            return [];
        }

        // Determine cache key and check cache
        $isClassOnlyLookup = ($method === null || $method === '__invoke'); // Treat PSR-15 and Invokable similarly for *initial* fetch scope
        $cacheKey = $controllerClass . '@' . ($method ?? '__CLASS_ONLY__'); // Unique key

        // Use methodCache for specific actions, controllerCache for class-level only
        // Note: We fetch combined and store in methodCache even for __invoke if attributes exist there.
        if (isset($this->methodCache[$cacheKey])) {
            return $this->methodCache[$cacheKey];
        }
        // If looking for class-only AND method cache missed, check controllerCache as fallback
        if ($isClassOnlyLookup && isset($this->controllerCache[$controllerClass])) {
            // If we only needed class-level, and it's cached, return it.
            // This prevents re-fetching class attributes unnecessarily.
            // We will still check __invoke attributes below if method is __invoke.
            // Let's simplify: Always fetch and combine, then cache under the specific key.
            // Remove this controllerCache check here to ensure method attributes (like __invoke) are always checked if relevant.
        }


        // --- Fetch Middleware Attributes ---
        // Always get class-level attributes first (uses its own cache: controllerCache)
        $controllerMiddleware = $this->getControllerMiddleware($controllerClass);

        $methodMiddleware = [];
        $actualMethodToReflect = null;

        if ($method && $method !== '__invoke' && method_exists($controllerClass, $method)) {
            // Specific, non-magic method name
            $actualMethodToReflect = $method;
        } elseif ($method === '__invoke' && method_exists($controllerClass, '__invoke')) {
            // Explicitly check for attributes on __invoke if requested
            $actualMethodToReflect = '__invoke';
        }
        // If $method is null (PSR-15), we don't look for method attributes.

        if ($actualMethodToReflect) {
            // Fetch attributes only for the relevant method
            $methodMiddleware = $this->getMethodMiddleware($controllerClass, $actualMethodToReflect);
        }

        // --- Combine and Cache ---
        // Merge results. Method attributes typically add to or override class attributes.
        // A simple merge might suffice, or you might implement more complex override logic if needed.
        $combinedMiddleware = array_merge($controllerMiddleware, $methodMiddleware);

        // Cache the final combined list under the specific key (e.g., Controller@action, Controller@__invoke, Controller@__CLASS_ONLY__)
        $this->methodCache[$cacheKey] = $combinedMiddleware;

        // Note: controllerCache in getControllerMiddleware still caches purely class-level attributes.
        // methodCache stores the potentially combined result.

        return $combinedMiddleware;
    }

    /**
     * Get middleware configurations defined via attributes on the controller class.
     * Uses caching.
     *
     * @param class-string $controllerClass The fully qualified class name.
     * @return list<AttributeMiddlewareConfig> List of middleware configurations from class attributes.
     */
    protected function getControllerMiddleware(string $controllerClass): array
    {
        // Return from cache if available.
        if (isset($this->controllerCache[$controllerClass])) {
            return $this->controllerCache[$controllerClass];
        }

        if (!class_exists($controllerClass)) {
            $this->logger->warning("Controller class not found when checking attributes: {$controllerClass}");
            $this->controllerCache[$controllerClass] = []; // Cache empty result
            return [];
        }

        $middlewareConfigs = [];
        try {
            // Use reflection cache if available, otherwise create and store
            if (!isset($this->reflectionCache[$controllerClass])) {
                $reflectionClass = new ReflectionClass($controllerClass);
                $constructor = $reflectionClass->getConstructor();
                $this->reflectionCache[$controllerClass] = [
                    'class' => $reflectionClass,
                    'constructor' => $constructor,
                    'parameters' => $constructor ? $constructor->getParameters() : []
                ];
            } else {
                $reflectionClass = $this->reflectionCache[$controllerClass]['class'];
            }

            // Process #[Middleware] attributes
            $middlewareAttributes = $reflectionClass->getAttributes(Middleware::class);
            foreach ($middlewareAttributes as $attribute) {
                /** @var Middleware $instance */
                $instance = $attribute->newInstance();
                $middlewareDefs = $instance->getMiddleware(); // Can be string or list<string>
                $parameters = $instance->getParameters(); // array<mixed>

                // Normalize single definition to list for consistent handling
                $defs = is_array($middlewareDefs) ? $middlewareDefs : [$middlewareDefs];

                // Create config for each class specified in the attribute
                foreach ($defs as $def) {
                    if (is_string($def)) { // Ensure it's a string (class name)
                        $middlewareConfigs[] = [
                            'class' => $def, // Store single class name here
                            'parameters' => $parameters
                        ];
                    }
                }
            }

            // Process #[MiddlewareGroup] attributes
            $groupAttributes = $reflectionClass->getAttributes(MiddlewareGroup::class);
            foreach ($groupAttributes as $attribute) {
                /** @var MiddlewareGroup $instance */
                $instance = $attribute->newInstance();
                $middlewareConfigs[] = [
                    'group' => $instance->getGroupName(), // string
                    'parameters' => [] // Groups don't have parameters via attributes
                ];
            }

        } catch (Throwable $e) { // Catch ReflectionException and others
            $this->logger->error("Error reflecting or processing controller attributes: {$e->getMessage()}", [
                'controller' => $controllerClass,
                'exception' => $e
            ]);
            // Return empty on error to avoid partial results
            $middlewareConfigs = [];
        }

        // Cache the result (even if empty or error occurred)
        $this->controllerCache[$controllerClass] = $middlewareConfigs;

        return $middlewareConfigs;
    }


    /**
     * Get middleware configurations defined via attributes on a specific controller method.
     *
     * @param class-string $controllerClass The fully qualified class name.
     * @param string $method The method name.
     * @return list<AttributeMiddlewareConfig> List of middleware configurations from method attributes.
     */
    protected function getMethodMiddleware(string $controllerClass, string $method): array
    {
        // Basic validation
        if (!method_exists($controllerClass, $method)) {
            $this->logger->debug("Method not found when checking attributes: {$controllerClass}::{$method}");
            return [];
        }

        $middlewareConfigs = [];
        try {
            // ReflectionMethod doesn't have a dedicated cache here, created on demand.
            $reflectionMethod = new ReflectionMethod($controllerClass, $method);

            // Process #[Middleware] attributes
            $middlewareAttributes = $reflectionMethod->getAttributes(Middleware::class);
            foreach ($middlewareAttributes as $attribute) {
                /** @var Middleware $instance */
                $instance = $attribute->newInstance();
                $middlewareDefs = $instance->getMiddleware(); // string | list<string>
                $parameters = $instance->getParameters();   // array<mixed>

                // Normalize single definition to list for consistent handling
                $defs = is_array($middlewareDefs) ? $middlewareDefs : [$middlewareDefs];

                // Create config for each class specified in the attribute
                foreach ($defs as $def) {
                    if (is_string($def)) {
                        $middlewareConfigs[] = [
                            'class' => $def,
                            'parameters' => $parameters
                        ];
                    }
                }
            }

            // Process #[MiddlewareGroup] attributes
            $groupAttributes = $reflectionMethod->getAttributes(MiddlewareGroup::class);
            foreach ($groupAttributes as $attribute) {
                /** @var MiddlewareGroup $instance */
                $instance = $attribute->newInstance();
                $middlewareConfigs[] = [
                    'group' => $instance->getGroupName(), // string
                    'parameters' => []
                ];
            }

        } catch (Throwable $e) { // Catch ReflectionException and others
            $this->logger->error("Error reflecting or processing method attributes: {$e->getMessage()}", [
                'controller' => $controllerClass,
                'method' => $method,
                'exception' => $e
            ]);
            // Return empty on error
            $middlewareConfigs = [];
        }

        return $middlewareConfigs;
    }

    /**
     * Load middleware configuration from an array structure.
     *
     * @param array{
     * named?: array<string, MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}>,
     * groups?: array<string, list<MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}>>,
     * global?: list<MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}>
     * } $config Configuration array.
     * @return $this
     */
    public function fromConfig(array $config): self
    {
        // Register named middleware
        if (isset($config['named']) && is_array($config['named'])) {
            // Process potential MiddlewareConfig definitions within named middleware
            $processedNamed = [];
            foreach ($config['named'] as $name => $middleware) {
                $processedNamed[$name] = $this->processSingleMiddlewareConfig($middleware);
            }
            $this->registerNamedMiddleware($processedNamed);
        }

        // Register groups
        if (isset($config['groups']) && is_array($config['groups'])) {
            $this->registerGroupedMiddleware($config['groups']); // This already handles processing
        }

        // Register global middleware
        if (isset($config['global']) && is_array($config['global'])) {
            $processedGlobals = $this->processMiddlewareConfig($config['global']);
            foreach ($processedGlobals as $middleware) {
                $this->global($middleware); // Assumes processMiddlewareConfig returns MiddlewareDefinition items
            }
        }

        return $this;
    }

    /**
     * Registers named middleware definitions.
     *
     * @param array<string, MiddlewareDefinition> $nameMiddleware Map of alias => definition.
     * @return void
     */
    public function registerNamedMiddleware(array $nameMiddleware): void
    {
        foreach ($nameMiddleware as $name => $middleware) {
            // The middleware definition should already be processed (string, callable, instance, MiddlewareConfig)
            $this->named($name, $middleware);
        }
    }

    /**
     * Registers grouped middleware definitions.
     * Processes potential configuration arrays within the groups.
     *
     * @param array<string, list<MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}>> $groupedMiddleware Map of group name => list of definitions.
     * @return void
     */
    private function registerGroupedMiddleware(array $groupedMiddleware): void
    {
        foreach ($groupedMiddleware as $name => $middlewareList) {
            if (is_array($middlewareList)) {
                // Process the list to handle potential config arrays
                $processed = $this->processMiddlewareConfig($middlewareList);
                $this->group($name, $processed);
            } else {
                $this->logger->warning("Invalid format for middleware group '{$name}'. Expected an array/list.");
            }
        }
    }

    /**
     * Process a list of middleware definitions, converting array configurations
     * into MiddlewareConfig objects.
     *
     * @param list<MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}|array{0: class-string, 1: array<mixed>}> $middlewareList Raw list from config.
     * @return list<MiddlewareDefinition> Processed list suitable for internal storage.
     */
    protected function processMiddlewareConfig(array $middlewareList): array
    {
        $result = [];
        foreach ($middlewareList as $middleware) {
            $result[] = $this->processSingleMiddlewareConfig($middleware);
        }
        // Ensure it's a list
        return array_values($result);
    }

    /**
     * Process a single middleware definition from config, converting array formats
     * to MiddlewareConfig if necessary.
     *
     * @param MiddlewareDefinition|array{class: class-string, parameters?: array<mixed>}|array{0: class-string, 1: array<mixed>} $middleware Raw definition from config.
     * @return MiddlewareDefinition Processed definition (string, callable, instance, or MiddlewareConfig).
     */
    protected function processSingleMiddlewareConfig($middleware)
    {
        // Format: ['class' => ClassName::class, 'parameters' => [...]]
        if (is_array($middleware) && isset($middleware['class']) && is_string($middleware['class'])) {
            $middlewareClass = $middleware['class'];
            $parameters = $middleware['parameters'] ?? [];
            if (!is_array($parameters)) {
                $this->logger->warning("Invalid 'parameters' format for middleware '{$middlewareClass}'. Expected array.", ['parameters' => $parameters]);
                $parameters = []; // Default to empty array on invalid format
            }
            return new MiddlewareConfig($middlewareClass, $parameters);
        } // Format: [ClassName::class, [...parameters]]
        elseif (is_array($middleware) && count($middleware) === 2 && is_string($middleware[0]) && class_exists($middleware[0]) && is_array($middleware[1])) {
            $middlewareClass = $middleware[0];
            $parameters = $middleware[1];
            return new MiddlewareConfig($middlewareClass, $parameters);
        } // Already in a valid format (string, callable, instance, MiddlewareConfig)
        elseif (is_string($middleware) || is_callable($middleware) || $middleware instanceof MiddlewareInterface || $middleware instanceof MiddlewareConfig) {
            return $middleware;
        } else {
            // Log invalid format and skip (or throw exception)
            $type = is_object($middleware) ? get_class($middleware) : gettype($middleware);
            $this->logger->error("Skipping invalid middleware definition format in configuration.", ['definition' => $middleware, 'type' => $type]);
            // Return null or throw? Returning null means it needs filtering later. Let's throw to be stricter.
            throw new RuntimeException("Invalid middleware definition format encountered in configuration: type '{$type}'");
        }
    }
}

// Helper class assumed to exist for MiddlewareConfig (can be defined elsewhere)
// namespace Ody\Middleware;
// class MiddlewareConfig {
//     public function __construct(public string $class, public array $parameters = []) {}
//     public function getClass(): string { return $this->class; }
//     public function getParameters(): array { return $this->parameters; }
// }