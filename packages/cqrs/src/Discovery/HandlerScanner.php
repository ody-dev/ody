<?php

namespace Ody\CQRS\Discovery;

use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class HandlerScanner
{
    /**
     * @var ClassNameResolver
     */
    private ClassNameResolver $classNameResolver;

    /**
     * @param CommandHandlerRegistry $commandRegistry
     * @param QueryHandlerRegistry $queryRegistry
     * @param EventHandlerRegistry $eventRegistry
     * @param FileScanner $fileScanner
     * @param ClassNameResolver $classNameResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private CommandHandlerRegistry $commandRegistry,
        private QueryHandlerRegistry   $queryRegistry,
        private EventHandlerRegistry   $eventRegistry,
        private FileScanner            $fileScanner,
        ClassNameResolver              $classNameResolver,
        private LoggerInterface        $logger
    )
    {
        $this->classNameResolver = $classNameResolver;
    }

    /**
     * Scan specified paths and register handlers
     *
     * @param array $paths
     * @return void
     */
    public function scanAndRegister(array $paths): void
    {
        foreach ($paths as $path) {
            $this->scanPath($path);
        }
    }

    /**
     * Scan a path for handler classes
     *
     * @param string $path
     * @return void
     */
    private function scanPath(string $path): void
    {
        $files = $this->fileScanner->scanDirectory($path);

        foreach ($files as $file) {
            $className = $this->classNameResolver->resolveFromFile($file);

            if (!$className || !class_exists($className)) {
                continue;
            }

            $this->registerHandlersInClass($className);
        }
    }

    /**
     * Register handlers from a class
     *
     * @param string $className
     * @return void
     */
    private function registerHandlersInClass(string $className): void
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Check for CommandHandler attribute
                $commandAttributes = $method->getAttributes(CommandHandler::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($commandAttributes as $attribute) {
                    $this->registerCommandHandler($reflectionClass, $method);
                }

                // Check for QueryHandler attribute
                $queryAttributes = $method->getAttributes(QueryHandler::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($queryAttributes as $attribute) {
                    $this->registerQueryHandler($reflectionClass, $method);
                }

                // Check for EventHandler attribute
                $eventAttributes = $method->getAttributes(EventHandler::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($eventAttributes as $attribute) {
                    $this->registerEventHandler($reflectionClass, $method);
                }
            }
        } catch (Throwable $e) {
            // Log error but continue scanning
            $this->logger->error("Error scanning class {$className}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Register a command handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @return void
     */
    private function registerCommandHandler(ReflectionClass $class, ReflectionMethod $method): void
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $commandClass = $params[0]->getType()->getName();
            $this->commandRegistry->registerHandler(
                $commandClass,
                $class->getName(),
                $method->getName()
            );
        }
    }

    /**
     * Register a query handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @return void
     */
    private function registerQueryHandler(ReflectionClass $class, ReflectionMethod $method): void
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $queryClass = $params[0]->getType()->getName();
            $this->queryRegistry->registerHandler(
                $queryClass,
                $class->getName(),
                $method->getName()
            );
        }
    }

    /**
     * Register an event handler
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @return void
     */
    private function registerEventHandler(ReflectionClass $class, ReflectionMethod $method): void
    {
        $params = $method->getParameters();

        if (empty($params) || !$params[0]->getType() || !$params[0]->getType()->isBuiltin()) {
            $eventClass = $params[0]->getType()->getName();
            $this->eventRegistry->registerHandler(
                $eventClass,
                $class->getName(),
                $method->getName()
            );
        }
    }
}