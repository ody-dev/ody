<?php

namespace Ody\CQRS\Tests\Unit\Middleware;

use Ody\Container\Container;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use Ody\CQRS\Middleware\MiddlewareRegistry;
use PHPUnit\Framework\TestCase;

class MiddlewareProcessorTest extends TestCase
{
    private $container;
    private $registry;
    private $processor;

    public function testProcessWithNoMiddleware(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $originalCallback = function ($args) {
            return 'Original result: ' . implode(', ', $args);
        };

        // Setup registry to return no interceptors
        $this->registry->method('getBeforeInterceptors')->willReturn([]);
        $this->registry->method('getAroundInterceptors')->willReturn([]);
        $this->registry->method('getAfterInterceptors')->willReturn([]);
        $this->registry->method('getAfterThrowingInterceptors')->willReturn([]);

        $result = $this->processor->process($target, $method, $arguments, $originalCallback);

        $this->assertEquals('Original result: arg1, arg2', $result);
    }

    public function testProcessWithBeforeInterceptor(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $originalCallback = function ($args) {
            return 'Original result: ' . implode(', ', $args);
        };

        // Create a before interceptor
        $beforeInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['before'])
            ->getMock();

        $beforeInterceptor->expects($this->once())
            ->method('before')
            ->with(...$arguments);

        // Setup registry to return our before interceptor
        $this->registry->method('getBeforeInterceptors')
            ->willReturn([
                ['class' => get_class($beforeInterceptor), 'method' => 'before']
            ]);
        $this->registry->method('getAroundInterceptors')->willReturn([]);
        $this->registry->method('getAfterInterceptors')->willReturn([]);
        $this->registry->method('getAfterThrowingInterceptors')->willReturn([]);

        // Setup container to return our interceptor
        $this->container->method('make')
            ->with(get_class($beforeInterceptor))
            ->willReturn($beforeInterceptor);

        $result = $this->processor->process($target, $method, $arguments, $originalCallback);

        $this->assertEquals('Original result: arg1, arg2', $result);
    }

    public function testProcessWithAfterInterceptor(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $originalResult = 'Original result';
        $modifiedResult = 'Modified result';

        $originalCallback = function ($args) use ($originalResult) {
            return $originalResult;
        };

        // Create an after interceptor
        $afterInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['after'])
            ->getMock();

        $afterInterceptor->expects($this->once())
            ->method('after')
            ->with($originalResult, $arguments)
            ->willReturn($modifiedResult);

        // Setup registry to return our after interceptor
        $this->registry->method('getBeforeInterceptors')->willReturn([]);
        $this->registry->method('getAroundInterceptors')->willReturn([]);
        $this->registry->method('getAfterInterceptors')
            ->willReturn([
                ['class' => get_class($afterInterceptor), 'method' => 'after']
            ]);
        $this->registry->method('getAfterThrowingInterceptors')->willReturn([]);

        // Setup container to return our interceptor
        $this->container->method('make')
            ->with(get_class($afterInterceptor))
            ->willReturn($afterInterceptor);

        $result = $this->processor->process($target, $method, $arguments, $originalCallback);

        $this->assertEquals($modifiedResult, $result);
    }

    public function testProcessWithAroundInterceptor(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $originalResult = 'Original result';
        $modifiedResult = 'Modified result';

        $originalCallback = function ($args) use ($originalResult) {
            return $originalResult;
        };

        // Create an around interceptor
        $aroundInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['around'])
            ->getMock();

        $aroundInterceptor->expects($this->once())
            ->method('around')
            ->with($this->isInstanceOf('Ody\CQRS\Middleware\DefaultMethodInvocation'))
            ->willReturnCallback(function ($invocation) use ($modifiedResult) {
                // Test the invocation object
                $this->assertInstanceOf('Ody\CQRS\Middleware\MethodInvocation', $invocation);

                // Proceed with the invocation but modify the result
                $originalResult = $invocation->proceed();
                return $modifiedResult;
            });

        // Setup registry to return our around interceptor
        $this->registry->method('getBeforeInterceptors')->willReturn([]);
        $this->registry->method('getAroundInterceptors')
            ->willReturn([
                ['class' => get_class($aroundInterceptor), 'method' => 'around']
            ]);
        $this->registry->method('getAfterInterceptors')->willReturn([]);
        $this->registry->method('getAfterThrowingInterceptors')->willReturn([]);

        // Setup container to return our interceptor
        $this->container->method('make')
            ->with(get_class($aroundInterceptor))
            ->willReturn($aroundInterceptor);

        $result = $this->processor->process($target, $method, $arguments, $originalCallback);

        $this->assertEquals($modifiedResult, $result);
    }

    public function testProcessWithAfterThrowingInterceptor(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $exception = new \RuntimeException('Test exception');

        $originalCallback = function ($args) use ($exception) {
            throw $exception;
        };

        // Create an afterThrowing interceptor
        $afterThrowingInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['afterThrowing'])
            ->getMock();

        $afterThrowingInterceptor->expects($this->once())
            ->method('afterThrowing')
            ->with($exception, $arguments);

        // Setup registry to return our afterThrowing interceptor
        $this->registry->method('getBeforeInterceptors')->willReturn([]);
        $this->registry->method('getAroundInterceptors')->willReturn([]);
        $this->registry->method('getAfterInterceptors')->willReturn([]);
        $this->registry->method('getAfterThrowingInterceptors')
            ->willReturn([
                ['class' => get_class($afterThrowingInterceptor), 'method' => 'afterThrowing']
            ]);

        // Setup container to return our interceptor
        $this->container->method('make')
            ->with(get_class($afterThrowingInterceptor))
            ->willReturn($afterThrowingInterceptor);

        // The exception should still be thrown after the interceptor is called
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->processor->process($target, $method, $arguments, $originalCallback);
    }

    public function testProcessWithAllInterceptorTypes(): void
    {
        $target = new \stdClass();
        $method = 'testMethod';
        $arguments = ['arg1', 'arg2'];
        $originalResult = 'Original result';
        $modifiedResult = 'Modified by around';
        $finalResult = 'Modified by after';

        $originalCallback = function ($args) use ($originalResult) {
            return $originalResult;
        };

        // Create different interceptor types
        $beforeInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['before'])
            ->getMock();

        $beforeInterceptor->expects($this->once())
            ->method('before')
            ->with(...$arguments);

        $aroundInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['around'])
            ->getMock();

        $aroundInterceptor->expects($this->once())
            ->method('around')
            ->willReturnCallback(function ($invocation) use ($modifiedResult) {
                $invocation->proceed(); // Original result is ignored
                return $modifiedResult;
            });

        $afterInterceptor = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['after'])
            ->getMock();

        $afterInterceptor->expects($this->once())
            ->method('after')
            ->with($modifiedResult, $arguments)
            ->willReturn($finalResult);

        // Store class names for mocks
        $beforeClass = get_class($beforeInterceptor);
        $aroundClass = get_class($aroundInterceptor);
        $afterClass = get_class($afterInterceptor);

        // Setup registry to return our interceptors
        $this->registry->method('getBeforeInterceptors')
            ->willReturn([
                ['class' => $beforeClass, 'method' => 'before']
            ]);
        $this->registry->method('getAroundInterceptors')
            ->willReturn([
                ['class' => $aroundClass, 'method' => 'around']
            ]);
        $this->registry->method('getAfterInterceptors')
            ->willReturn([
                ['class' => $afterClass, 'method' => 'after']
            ]);
        $this->registry->method('getAfterThrowingInterceptors')->willReturn([]);

        // Setup container to return our interceptors using a callback
        $this->container->method('make')
            ->willReturnCallback(function ($className) use ($beforeInterceptor, $aroundInterceptor, $afterInterceptor, $beforeClass, $aroundClass, $afterClass) {
                if ($className === $beforeClass) {
                    return $beforeInterceptor;
                }
                if ($className === $aroundClass) {
                    return $aroundInterceptor;
                }
                if ($className === $afterClass) {
                    return $afterInterceptor;
                }
                return null;
            });

        $result = $this->processor->process($target, $method, $arguments, $originalCallback);

        $this->assertEquals($finalResult, $result);
    }

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->registry = $this->createMock(MiddlewareRegistry::class);
        $this->processor = new MiddlewareProcessor($this->container, $this->registry);
    }
}