<?php

namespace Ody\CQRS\Middleware;

use Ody\Container\Container;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

class MiddlewareRegistry
{
    /**
     * @var array Before interceptors
     */
    private array $beforeInterceptors = [];

    /**
     * @var array Around interceptors
     */
    private array $aroundInterceptors = [];

    /**
     * @var array After interceptors
     */
    private array $afterInterceptors = [];

    /**
     * @var array AfterThrowing interceptors
     */
    private array $afterThrowingInterceptors = [];

    /**
     * @var PointcutResolver
     */
    private PointcutResolver $pointcutResolver;

    /**
     * @param Container $container
     * @param PointcutResolver $pointcutResolver
     */
    public function __construct(
        private Container $container,
        PointcutResolver  $pointcutResolver
    )
    {
        $this->pointcutResolver = $pointcutResolver;
    }

    /**
     * Register middleware classes from specified paths
     *
     * @param array $paths Paths to scan for middleware classes
     * @return void
     */
    public function registerMiddleware(array $paths): void
    {
        foreach ($paths as $path) {
            $this->scanDirectory($path);
        }

        // Sort interceptors by priority
        $this->sortInterceptors();
    }

    /**
     * Scan a directory for middleware classes
     *
     * @param string $path
     * @return void
     */
    private function scanDirectory(string $path): void
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

            $this->registerClassMiddleware($className);
        }

        // Scan subdirectories recursively
        $directories = glob($path . '/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $this->scanDirectory($directory);
        }
    }

    /**
     * Get the class name from a file
     *
     * @param string $file
     * @return string|null
     */
    private function getClassNameFromFile(string $file): ?string
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

    /**
     * Register middleware from a class
     *
     * @param string $className
     * @return void
     */
    private function registerClassMiddleware(string $className): void
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Check for Before attribute
                $beforeAttributes = $method->getAttributes(Before::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($beforeAttributes as $attribute) {
                    $this->registerBeforeInterceptor($reflectionClass, $method, $attribute->newInstance());
                }

                // Check for Around attribute
                $aroundAttributes = $method->getAttributes(Around::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($aroundAttributes as $attribute) {
                    $this->registerAroundInterceptor($reflectionClass, $method, $attribute->newInstance());
                }

                // Check for After attribute
                $afterAttributes = $method->getAttributes(After::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($afterAttributes as $attribute) {
                    $this->registerAfterInterceptor($reflectionClass, $method, $attribute->newInstance());
                }

                // Check for AfterThrowing attribute
                $afterThrowingAttributes = $method->getAttributes(AfterThrowing::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($afterThrowingAttributes as $attribute) {
                    $this->registerAfterThrowingInterceptor($reflectionClass, $method, $attribute->newInstance());
                }
            }
        } catch (\Throwable $e) {
            // Log error but continue scanning
            logger()->error("Error scanning class {$className}: " . $e->getMessage());
        }
    }

    /**
     * Register a Before interceptor
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param Before $attribute
     * @return void
     */
    private function registerBeforeInterceptor(
        ReflectionClass  $class,
        ReflectionMethod $method,
        Before           $attribute
    ): void
    {
        $this->beforeInterceptors[] = [
            'class' => $class->getName(),
            'method' => $method->getName(),
            'priority' => $attribute->getPriority(),
            'pointcut' => $attribute->getPointcut()
        ];
    }

    /**
     * Register an Around interceptor
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param Around $attribute
     * @return void
     */
    private function registerAroundInterceptor(
        ReflectionClass  $class,
        ReflectionMethod $method,
        Around           $attribute
    ): void
    {
        $this->aroundInterceptors[] = [
            'class' => $class->getName(),
            'method' => $method->getName(),
            'priority' => $attribute->getPriority(),
            'pointcut' => $attribute->getPointcut()
        ];
    }

    /**
     * Register an After interceptor
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param After $attribute
     * @return void
     */
    private function registerAfterInterceptor(
        ReflectionClass  $class,
        ReflectionMethod $method,
        After            $attribute
    ): void
    {
        $this->afterInterceptors[] = [
            'class' => $class->getName(),
            'method' => $method->getName(),
            'priority' => $attribute->getPriority(),
            'pointcut' => $attribute->getPointcut()
        ];
    }

    /**
     * Register an AfterThrowing interceptor
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @param AfterThrowing $attribute
     * @return void
     */
    private function registerAfterThrowingInterceptor(
        ReflectionClass  $class,
        ReflectionMethod $method,
        AfterThrowing    $attribute
    ): void
    {
        $this->afterThrowingInterceptors[] = [
            'class' => $class->getName(),
            'method' => $method->getName(),
            'priority' => $attribute->getPriority(),
            'pointcut' => $attribute->getPointcut()
        ];
    }

    /**
     * Sort all interceptors by priority
     *
     * @return void
     */
    private function sortInterceptors(): void
    {
        $sortByPriority = function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        };

        usort($this->beforeInterceptors, $sortByPriority);
        usort($this->aroundInterceptors, $sortByPriority);
        usort($this->afterInterceptors, $sortByPriority);
        usort($this->afterThrowingInterceptors, $sortByPriority);
    }

    /**
     * Get all Before interceptors that match the target
     *
     * @param string $targetClass
     * @param string $targetMethod
     * @return array
     */
    public function getBeforeInterceptors(string $targetClass, string $targetMethod): array
    {
        return $this->getMatchingInterceptors($this->beforeInterceptors, $targetClass, $targetMethod);
    }

    /**
     * Get interceptors that match the target class and method
     *
     * @param array $interceptors
     * @param string $targetClass
     * @param string $targetMethod
     * @return array
     */
    private function getMatchingInterceptors(array $interceptors, string $targetClass, string $targetMethod): array
    {
        $matching = [];

        foreach ($interceptors as $interceptor) {
            if ($this->pointcutResolver->matches($interceptor['pointcut'], $targetClass, $targetMethod)) {
                $matching[] = $interceptor;
            }
        }

        return $matching;
    }

    /**
     * Get all Around interceptors that match the target
     *
     * @param string $targetClass
     * @param string $targetMethod
     * @return array
     */
    public function getAroundInterceptors(string $targetClass, string $targetMethod): array
    {
        return $this->getMatchingInterceptors($this->aroundInterceptors, $targetClass, $targetMethod);
    }

    /**
     * Get all After interceptors that match the target
     *
     * @param string $targetClass
     * @param string $targetMethod
     * @return array
     */
    public function getAfterInterceptors(string $targetClass, string $targetMethod): array
    {
        return $this->getMatchingInterceptors($this->afterInterceptors, $targetClass, $targetMethod);
    }

    /**
     * Get all AfterThrowing interceptors that match the target
     *
     * @param string $targetClass
     * @param string $targetMethod
     * @return array
     */
    public function getAfterThrowingInterceptors(string $targetClass, string $targetMethod): array
    {
        return $this->getMatchingInterceptors($this->afterThrowingInterceptors, $targetClass, $targetMethod);
    }
}