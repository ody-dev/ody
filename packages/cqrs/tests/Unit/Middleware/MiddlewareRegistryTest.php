<?php

namespace Ody\CQRS\Tests\Unit\Middleware;

use Ody\Container\Container;
use Ody\CQRS\Middleware\After;
use Ody\CQRS\Middleware\AfterThrowing;
use Ody\CQRS\Middleware\Around;
use Ody\CQRS\Middleware\Before;
use Ody\CQRS\Middleware\MiddlewareRegistry;
use Ody\CQRS\Middleware\PointcutResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MiddlewareRegistryTest extends TestCase
{
    private $container;
    private $pointcutResolver;
    private $logger;
    private $registry;

    public function testGetBeforeInterceptors(): void
    {
        $targetClass = 'App\Service\UserService';
        $targetMethod = 'createUser';

        // Create reflection for test attribute
        $beforeAttribute = new Before(priority: 1, pointcut: 'App\Service\*');
        $interceptorClass = 'Test\BeforeInterceptor';
        $interceptorMethod = 'beforeMethod';

        // Set up private property with test data
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'beforeInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            [
                'class' => $interceptorClass,
                'method' => $interceptorMethod,
                'priority' => $beforeAttribute->getPriority(),
                'pointcut' => $beforeAttribute->getPointcut()
            ]
        ]);

        // Set up pointcut resolver to match our target
        $this->pointcutResolver->expects($this->once())
            ->method('matches')
            ->with($beforeAttribute->getPointcut(), $targetClass, $targetMethod)
            ->willReturn(true);

        $interceptors = $this->registry->getBeforeInterceptors($targetClass, $targetMethod);

        $this->assertCount(1, $interceptors);
        $this->assertEquals($interceptorClass, $interceptors[0]['class']);
        $this->assertEquals($interceptorMethod, $interceptors[0]['method']);
    }

    public function testGetAroundInterceptors(): void
    {
        $targetClass = 'App\Service\UserService';
        $targetMethod = 'createUser';

        // Create reflection for test attribute
        $aroundAttribute = new Around(priority: 1, pointcut: 'App\Service\*');
        $interceptorClass = 'Test\AroundInterceptor';
        $interceptorMethod = 'aroundMethod';

        // Set up private property with test data
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'aroundInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            [
                'class' => $interceptorClass,
                'method' => $interceptorMethod,
                'priority' => $aroundAttribute->getPriority(),
                'pointcut' => $aroundAttribute->getPointcut()
            ]
        ]);

        // Set up pointcut resolver to match our target
        $this->pointcutResolver->expects($this->once())
            ->method('matches')
            ->with($aroundAttribute->getPointcut(), $targetClass, $targetMethod)
            ->willReturn(true);

        $interceptors = $this->registry->getAroundInterceptors($targetClass, $targetMethod);

        $this->assertCount(1, $interceptors);
        $this->assertEquals($interceptorClass, $interceptors[0]['class']);
        $this->assertEquals($interceptorMethod, $interceptors[0]['method']);
    }

    public function testGetAfterInterceptors(): void
    {
        $targetClass = 'App\Service\UserService';
        $targetMethod = 'createUser';

        // Create reflection for test attribute
        $afterAttribute = new After(priority: 1, pointcut: 'App\Service\*');
        $interceptorClass = 'Test\AfterInterceptor';
        $interceptorMethod = 'afterMethod';

        // Set up private property with test data
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'afterInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            [
                'class' => $interceptorClass,
                'method' => $interceptorMethod,
                'priority' => $afterAttribute->getPriority(),
                'pointcut' => $afterAttribute->getPointcut()
            ]
        ]);

        // Set up pointcut resolver to match our target
        $this->pointcutResolver->expects($this->once())
            ->method('matches')
            ->with($afterAttribute->getPointcut(), $targetClass, $targetMethod)
            ->willReturn(true);

        $interceptors = $this->registry->getAfterInterceptors($targetClass, $targetMethod);

        $this->assertCount(1, $interceptors);
        $this->assertEquals($interceptorClass, $interceptors[0]['class']);
        $this->assertEquals($interceptorMethod, $interceptors[0]['method']);
    }

    public function testGetAfterThrowingInterceptors(): void
    {
        $targetClass = 'App\Service\UserService';
        $targetMethod = 'createUser';

        // Create reflection for test attribute
        $afterThrowingAttribute = new AfterThrowing(priority: 1, pointcut: 'App\Service\*');
        $interceptorClass = 'Test\AfterThrowingInterceptor';
        $interceptorMethod = 'afterThrowingMethod';

        // Set up private property with test data
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'afterThrowingInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            [
                'class' => $interceptorClass,
                'method' => $interceptorMethod,
                'priority' => $afterThrowingAttribute->getPriority(),
                'pointcut' => $afterThrowingAttribute->getPointcut()
            ]
        ]);

        // Set up pointcut resolver to match our target
        $this->pointcutResolver->expects($this->once())
            ->method('matches')
            ->with($afterThrowingAttribute->getPointcut(), $targetClass, $targetMethod)
            ->willReturn(true);

        $interceptors = $this->registry->getAfterThrowingInterceptors($targetClass, $targetMethod);

        $this->assertCount(1, $interceptors);
        $this->assertEquals($interceptorClass, $interceptors[0]['class']);
        $this->assertEquals($interceptorMethod, $interceptors[0]['method']);
    }

    public function testNoMatchingInterceptors(): void
    {
        $targetClass = 'App\Service\UserService';
        $targetMethod = 'createUser';

        // Create reflection for test attribute
        $beforeAttribute = new Before(priority: 1, pointcut: 'App\Other\*');
        $interceptorClass = 'Test\BeforeInterceptor';
        $interceptorMethod = 'beforeMethod';

        // Set up private property with test data
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'beforeInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            [
                'class' => $interceptorClass,
                'method' => $interceptorMethod,
                'priority' => $beforeAttribute->getPriority(),
                'pointcut' => $beforeAttribute->getPointcut()
            ]
        ]);

        // Set up pointcut resolver to NOT match our target
        $this->pointcutResolver->expects($this->once())
            ->method('matches')
            ->with($beforeAttribute->getPointcut(), $targetClass, $targetMethod)
            ->willReturn(false);

        $interceptors = $this->registry->getBeforeInterceptors($targetClass, $targetMethod);

        $this->assertEmpty($interceptors);
    }

    public function testInterceptorsOrderedByPriority(): void
    {
        // Since register middleware scans directories, we'll test the sorting directly

        // Create test interceptors with different priorities
        $highPriority = ['class' => 'HighPriorityInterceptor', 'method' => 'handle', 'priority' => 1, 'pointcut' => '*'];
        $mediumPriority = ['class' => 'MediumPriorityInterceptor', 'method' => 'handle', 'priority' => 5, 'pointcut' => '*'];
        $lowPriority = ['class' => 'LowPriorityInterceptor', 'method' => 'handle', 'priority' => 10, 'pointcut' => '*'];

        // Set interceptors in random order
        $reflectionProperty = new \ReflectionProperty(MiddlewareRegistry::class, 'beforeInterceptors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->registry, [
            $mediumPriority,
            $lowPriority,
            $highPriority
        ]);

        // Call private sortInterceptors method
        $method = new \ReflectionMethod(MiddlewareRegistry::class, 'sortInterceptors');
        $method->setAccessible(true);
        $method->invoke($this->registry);

        // Verify they're now sorted by priority (lowest first)
        $sortedInterceptors = $reflectionProperty->getValue($this->registry);
        $this->assertEquals('HighPriorityInterceptor', $sortedInterceptors[0]['class']);
        $this->assertEquals('MediumPriorityInterceptor', $sortedInterceptors[1]['class']);
        $this->assertEquals('LowPriorityInterceptor', $sortedInterceptors[2]['class']);
    }

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->pointcutResolver = $this->createMock(PointcutResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->registry = new MiddlewareRegistry(
            $this->container,
            $this->pointcutResolver,
            $this->logger
        );
    }
}