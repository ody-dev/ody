<?php

namespace Ody\CQRS\Providers;

use Enqueue\Client\ProducerInterface;
use Enqueue\SimpleClient\SimpleClient;
use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Enqueue\CommandProcessor;
use Ody\CQRS\Enqueue\Configuration;
use Ody\CQRS\Enqueue\EnqueueCommandBus;
use Ody\CQRS\Enqueue\EnqueueEventBus;
use Ody\CQRS\Enqueue\EnqueueQueryBus;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Interfaces\CommandBus as CommandBusInterface;
use Ody\CQRS\Interfaces\EventBus as EventBusInterface;
use Ody\CQRS\Interfaces\QueryBus as QueryBusInterface;
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
        error_log('boot');
        // Register handlers from configured directories
        $this->registerHandlers();
    }

    /**
     * Register the CQRS services.
     *
     * @return void
     */
    public function register(): void
    {
        error_log('register');
        // Register the Configuration
        $this->container->singleton(Configuration::class, function () {
            return new Configuration(config('cqrs'));
        });

        $this->container->singleton(ProducerInterface::class, function () {
            $client = new SimpleClient('amqp://admin:password@localhost:5672'); // TODO: hardcoded for now
            $client->setupBroker();
            return $client->getProducer();
        });

        $this->container->singleton(CommandProcessor::class);

        // Register the handler registries
        $this->container->singleton(CommandHandlerRegistry::class);
        $this->container->singleton(QueryHandlerRegistry::class);
        $this->container->singleton(EventHandlerRegistry::class);

        // Register the handler resolvers
        $this->container->singleton(CommandHandlerResolver::class, function ($app) {
            return new CommandHandlerResolver(
                $app,
                $app->make(EventBusInterface::class)
            );
        });
        $this->container->singleton(QueryHandlerResolver::class);

        // Register the buses
        $this->container->singleton(CommandBusInterface::class, function ($app) {
            return new EnqueueCommandBus(
                $app->make(ProducerInterface::class),
                $app->make(CommandHandlerRegistry::class),
                $app->make(CommandHandlerResolver::class),
                $app,
                $app->make(Configuration::class)
            );
        });

        $this->container->singleton(QueryBusInterface::class, function ($app) {
            return new EnqueueQueryBus(
                $app->make(ProducerInterface::class),
                $app->make(QueryHandlerRegistry::class),
                $app->make(QueryHandlerResolver::class),
                $app,
                $app->make(Configuration::class)
            );
        });

        $this->container->singleton(EventBusInterface::class, function ($app) {
            return new EnqueueEventBus(
                $app->make(ProducerInterface::class),
                $app->make(EventHandlerRegistry::class),
                $app,
                $app->make(Configuration::class)
            );
        });
    }

    /**
     * Register handlers by scanning service classes for attributes
     *
     * @return void
     */
    protected function registerHandlers()
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
            $this->container->make('log')->error("Error scanning class {$className}: " . $e->getMessage());
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