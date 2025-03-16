<?php

namespace Ody\Foundation\Providers;

use Ody\Container\Container;

/**
 * Base Service Provider
 *
 * A simplified abstract class that acts as a boilerplate for users creating service providers.
 * This class focuses on being a clear interface with minimal complexity.
 */
abstract class ServiceProvider
{
    /**
     * The application container instance.
     *
     * @var Container
     */
    public Container $container;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected bool $defer = false;

    /**
     * Services that should be registered as singletons
     *
     * @var array<string, mixed>
     */
    protected array $singletons = [];

    /**
     * Services that should be registered as bindings
     *
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * Services that should be registered as aliases
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Tags for organizing services
     *
     * @var array<string, array<string>>
     */
    protected array $tags = [];

    /**
     * Initialize the provider.
     *
     * @param Container|null $container Optional container instance
     * @return void
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? Container::getInstance();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    abstract public function boot(): void;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Load routes from the specified path.
     *
     * @param string $path Path to a route file or directory
     * @param array $attributes Optional route group attributes
     * @return void
     */
    protected function loadRoutesFrom(string $path, array $attributes = []): void
    {
        if (!file_exists($path)) {
            return;
        }

        $router = $this->container->make('router');
        $routeLoader = $this->container->make('route.loader');

        if (!empty($attributes)) {
            $router->group($attributes, function () use ($routeLoader, $path) {
                $routeLoader->load($path);
            });
        } else {
            $routeLoader->load($path);
        }
    }

    /**
     * Load routes from a specific path or directory.
     *
     * @param string $path Path to a route file or directory
     * @param array $attributes Optional route group attributes
     * @return void
     */
    protected function loadRoutes(string $path, array $attributes = []): void
    {
        // Check if the RouteServiceProvider is registered
        if ($this->container->has('Ody\Foundation\Providers\RouteServiceProvider')) {
            // Use the provider's method if available
            $routeServiceProvider = $this->container->make('Ody\Foundation\Providers\RouteServiceProvider');
            if (method_exists($routeServiceProvider, 'loadRoutes')) {
                $routeServiceProvider->loadRoutes($path, $attributes);
                return;
            }
        }

        // Fallback to the RouteLoader if available
        if ($this->container->has('route.loader')) {
            $routeLoader = $this->container->make('route.loader');

            if (is_dir($path)) {
                $routeLoader->loadDirectory($path, $attributes);
            } else {
                $routeLoader->load($path, $attributes);
            }
        } else {
            // Fallback to loadRoutesFrom for backward compatibility
            $this->loadRoutesFrom($path, $attributes);
        }
    }
    /**
     * Set up the provider with a container.
     * This method is for compatibility with older code.
     *
     * @param Container $container
     * @return void
     */
    public function setup(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Run common registration logic when a provider is registered
     *
     * @return void
     */
    public function registerCommon(): void
    {
        // Register singletons
        foreach ($this->singletons as $abstract => $concrete) {
            if (!$this->container->bound($abstract)) {
                $this->singleton($abstract, $concrete);
            }
        }

        // Register bindings
        foreach ($this->bindings as $abstract => $concrete) {
            if (!$this->container->bound($abstract)) {
                $this->bind($abstract, $concrete);
            }
        }

        // Register aliases
        foreach ($this->aliases as $alias => $abstract) {
            if (!$this->container->bound($alias)) {
                $this->alias($abstract, $alias);
            }
        }

        // Register tags
        foreach ($this->tags as $tag => $abstracts) {
            foreach ($abstracts as $abstract) {
                $this->container->tag($abstract, $tag);
            }
        }
    }


    /**
     * Register a binding with the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @param bool $shared
     * @return void
     */
    protected function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->container->bind($abstract, $concrete, $shared);
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    protected function singleton(string $abstract, $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    protected function instance(string $abstract, $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Alias a type to a shorter name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    protected function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }

    /**
     * Get a service from the container.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Check if a binding exists in the container.
     *
     * @param string $abstract
     * @return bool
     */
    protected function has(string $abstract): bool
    {
        return $this->container->bound($abstract) || $this->container->has($abstract);
    }
}