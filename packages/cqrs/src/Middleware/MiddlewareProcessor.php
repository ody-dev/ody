<?php

namespace Ody\CQRS\Middleware;

use Ody\Container\Container;

class MiddlewareProcessor
{
    /**
     * @param Container $container
     * @param MiddlewareRegistry $registry
     */
    public function __construct(
        private Container          $container,
        private MiddlewareRegistry $registry
    )
    {
    }

    /**
     * Process middleware for a method invocation
     *
     * @param object $target The target object
     * @param string $method The method name
     * @param array $arguments The method arguments
     * @param callable $originalCallback The original method callback
     * @return mixed
     */
    public function process(
        object   $target,
        string   $method,
        array    $arguments,
        callable $originalCallback
    ): mixed
    {
        $targetClass = get_class($target);

        // Get all applicable interceptors
        $beforeInterceptors = $this->registry->getBeforeInterceptors($targetClass, $method);
        $aroundInterceptors = $this->registry->getAroundInterceptors($targetClass, $method);
        $afterInterceptors = $this->registry->getAfterInterceptors($targetClass, $method);
        $afterThrowingInterceptors = $this->registry->getAfterThrowingInterceptors($targetClass, $method);

        // Create final method execution with after advice
        $finalCallback = function ($args) use (
            $originalCallback,
            $targetClass,
            $method,
            $afterInterceptors,
            $afterThrowingInterceptors
        ) {
            try {
                $result = $originalCallback($args);

                // Execute after interceptors
                foreach ($afterInterceptors as $interceptorInfo) {
                    $interceptor = $this->container->make($interceptorInfo['class']);
                    $interceptorMethod = $interceptorInfo['method'];

                    // Call the after interceptor with the result and arguments
                    $interceptorResult = $interceptor->$interceptorMethod($result, $args);

                    // Allow after interceptors to modify the result
                    if ($interceptorResult !== null) {
                        $result = $interceptorResult;
                    }
                }

                return $result;
            } catch (\Throwable $exception) {
                // Handle exception with afterThrowing interceptors
                $this->processAfterThrowing(
                    $exception,
                    $targetClass,
                    $method,
                    $afterThrowingInterceptors,
                    $args
                );

                // Re-throw the exception after processing
                throw $exception;
            }
        };

        // Apply around interceptors in reverse order (last registered executes first)
        $chain = $finalCallback;
        foreach (array_reverse($aroundInterceptors) as $interceptorInfo) {
            $interceptor = $this->container->make($interceptorInfo['class']);
            $interceptorMethod = $interceptorInfo['method'];

            // Create a new chain that includes this interceptor
            $chain = function ($args) use (
                $interceptor,
                $interceptorMethod,
                $chain,
                $target,
                $method,
                $arguments
            ) {
                $invocation = new DefaultMethodInvocation($target, $method, $arguments, $chain);
                return $interceptor->$interceptorMethod($invocation);
            };
        }

        // Execute before interceptors
        foreach ($beforeInterceptors as $interceptorInfo) {
            $interceptor = $this->container->make($interceptorInfo['class']);
            $interceptorMethod = $interceptorInfo['method'];

            // Call the before interceptor with the arguments
            $interceptor->$interceptorMethod(...$arguments);
        }

        // Execute the chain
        return $chain($arguments);
    }

    /**
     * Process afterThrowing interceptors
     *
     * @param \Throwable $exception
     * @param string $targetClass
     * @param string $method
     * @param array $afterThrowingInterceptors
     * @param array $arguments
     * @return void
     */
    private function processAfterThrowing(
        \Throwable $exception,
        string     $targetClass,
        string     $method,
        array      $afterThrowingInterceptors,
        array      $arguments
    ): void
    {
        foreach ($afterThrowingInterceptors as $interceptorInfo) {
            $interceptor = $this->container->make($interceptorInfo['class']);
            $interceptorMethod = $interceptorInfo['method'];

            // Call the afterThrowing interceptor with the exception and arguments
            $interceptor->$interceptorMethod($exception, $arguments);
        }
    }
}