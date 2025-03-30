<?php

namespace Ody\CQRS\Providers;

use Ody\Container\Contracts\BindingResolutionException;
use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Bus\CommandBus;
use Ody\CQRS\Bus\EventBus;
use Ody\CQRS\Bus\QueryBus;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Interfaces\EventBusInterface;
use Ody\CQRS\Interfaces\QueryBusInterface;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use Ody\CQRS\Middleware\MiddlewareRegistry;
use Ody\CQRS\Middleware\PointcutResolver;
use Ody\CQRS\Middleware\SimplePointcutResolver;
use Ody\Foundation\Providers\ServiceProvider;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

class CQRSServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the CQRS services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Register config file
        $this->publishes([
            __DIR__ . '/../../config/cqrs.php' => config_path('cqrs.php'),
        ], 'ody/cqrs');

        // Register handlers from configured directories
        $this->registerHandlers();

        // Register middleware
        $this->registerMiddleware();
    }

    /**
     * Register the CQRS services.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Register middleware components
        $this->container->singleton(PointcutResolver::class, SimplePointcutResolver::class);
        $this->container->singleton(MiddlewareRegistry::class);
        $this->container->singleton(MiddlewareProcessor::class);

        // Register the registries first since they have no dependencies
        $this->container->singleton(CommandHandlerRegistry::class);
        $this->container->singleton(QueryHandlerRegistry::class);
        $this->container->singleton(EventHandlerRegistry::class);

        // Register the query handler resolver
        $this->container->singleton(QueryHandlerResolver::class);

        // First register EventBus
        $this->container->singleton(EventBusInterface::class, function ($app) {
            return new EventBus(
                $app->make(EventHandlerRegistry::class),
                $this->container,
                $app->make(MiddlewareProcessor::class)
            );
        });

        // Then register CommandHandlerResolver that might need EventBus
        $this->container->singleton(CommandHandlerResolver::class, function ($app) {
            return new CommandHandlerResolver(
                $app
            );
        });

        // Finally register CommandBus and QueryBus
        $this->container->singleton(CommandBusInterface::class, function ($app) {
            return new CommandBus(
                $app->make(CommandHandlerRegistry::class),
                $app->make(CommandHandlerResolver::class),
                $app->make(MiddlewareProcessor::class)
            );
        });

        $this->container->singleton(QueryBusInterface::class, function ($app) {
            return new QueryBus(
                $app->make(QueryHandlerRegistry::class),
                $app->make(QueryHandlerResolver::class),
                $app->make(MiddlewareProcessor::class)
            );
        });
    }

    /**
     * Register handlers by scanning service classes for attributes
     *
     * @return void
     * @throws BindingResolutionException
     */
    protected function registerHandlers(): void
    {
        $handlerPaths = config('cqrs.handler_paths', []);

        if (empty($handlerPaths)) {
            return;
        }

        $commandRegistry = $this->container->make(CommandHandlerRegistry::class);
        $queryRegistry = $this->container->make(QueryHandlerRegistry::class);
        $eventRegistry = $this->container->make(EventHandlerRegistry::class);

        foreach ($handlerPaths as $path) {
            $this->scanDirectory($path, $commandRegistry, $queryRegistry, $eventRegistry);
        }
    }

    /**
     * Register middleware by scanning middleware classes for attributes
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        $middlewarePaths = config('cqrs.middleware_paths', []);

        if (empty($middlewarePaths)) {
            return;
        }

        /** @var MiddlewareRegistry $middlewareRegistry */
        $middlewareRegistry = $this->container->make(MiddlewareRegistry::class);
        $middlewareRegistry->registerMiddleware($middlewarePaths);
    }

    /**
     * Scan a directory for handler classes
     *
     * @param string $path
     * @param CommandHandlerRegistry $commandRegistry
     * @param QueryHandlerRegistry $queryRegistry
     * @param EventHandlerRegistry $eventRegistry
     * @return void
     */
    protected function scanDirectory(
        string                 $path,
        CommandHandlerRegistry $commandRegistry,
        QueryHandlerRegistry   $queryRegistry,
        EventHandlerRegistry   $eventRegistry
    )
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if (!$className || !class_exists($className)) {
                continue;
            }

            $this->registerClassHandlers(
                $className,
                $commandRegistry,
                $queryRegistry,
                $eventRegistry
            );
        }

        // Scan subdirectories recursively
        $directories = glob($path . '/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $this->scanDirectory(
                $directory,
                $commandRegistry,
                $queryRegistry,
                $eventRegistry
            );
        }
    }

    /**
     * Register handlers from a class
     *
     * @param string $className
     * @param CommandHandlerRegistry $commandRegistry
     * @param QueryHandlerRegistry $queryRegistry
     * @param EventHandlerRegistry $eventRegistry
     * @return void
     */
    protected function registerClassHandlers(
        string                 $className,
        CommandHandlerRegistry $commandRegistry,
        QueryHandlerRegistry   $queryRegistry,
        EventHandlerRegistry   $eventRegistry
    )
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Check for CommandHandler attribute
                $commandAttributes = $method->getAttributes(CommandHandler::class, ReflectionAttribute::IS_INSTANCEOF);

                foreach ($commandAttributes as $attribute) {
                    $this->registerCommandHandler($reflectionClass, $method, $commandRegistry);
                }

                // Check for QueryHandler attribute
                $queryAttributes = $method->getAttributes(QueryHandler::class, ReflectionAttribute::IS_INSTANCEOF);

                foreach ($queryAttributes as $attribute) {
                    $this->registerQueryHandler($reflectionClass, $method, $queryRegistry);
                }

                // Check for EventHandler attribute
                $eventAttributes = $method->getAttributes(EventHandler::class, ReflectionAttribute::IS_INSTANCEOF);

                foreach ($eventAttributes as $attribute) {
                    $this->registerEventHandler($reflectionClass, $method, $eventRegistry);
                }
            }
        } catch (\Throwable $e) {
            // Log error but continue scanning
            logger()->error("Error scanning class {$className}: " . $e->getMessage());
        }
    }

    /**
     * Register a command handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param CommandHandlerRegistry $registry
     * @return void
     */
    protected function registerCommandHandler(
        ReflectionClass        $class,
        ReflectionMethod       $method,
        CommandHandlerRegistry $registry
    )
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $commandClass = $params[0]->getType()->getName();
            $registry->registerHandler($commandClass, $class->getName(), $method->getName());
        }
    }

    /**
     * Register a query handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param QueryHandlerRegistry $registry
     * @return void
     */
    protected function registerQueryHandler(
        ReflectionClass      $class,
        ReflectionMethod     $method,
        QueryHandlerRegistry $registry
    )
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $queryClass = $params[0]->getType()->getName();
            $registry->registerHandler($queryClass, $class->getName(), $method->getName());
        }
    }

    /**
     * Register an event handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param EventHandlerRegistry $registry
     * @return void
     */
    protected function registerEventHandler(
        ReflectionClass      $class,
        ReflectionMethod     $method,
        EventHandlerRegistry $registry
    )
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $eventClass = $params[0]->getType()->getName();
            $registry->registerHandler($eventClass, $class->getName(), $method->getName());
        }
    }

    /**
     * Get the class name from a file
     *
     * @param string $file
     * @return string|null
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $namespace = '';
        $className = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_NAME_QUALIFIED) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break;
                    }
                }
                break;
            }
        }

        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return null;
    }
}