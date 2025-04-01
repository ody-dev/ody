<?php

namespace ODY\CQRS\Tests\Unit\Handler\Resolver;

use Ody\Container\Container;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use PHPUnit\Framework\TestCase;

class QueryHandlerResolverTest extends TestCase
{
    private $container;
    private $resolver;

    public function testResolveHandler(): void
    {
        // Simple handler class
        $handlerClass = get_class(new class {
            public function handle($query)
            {
                return ['result' => 'test'];
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $query = new \stdClass();

        $this->container->expects($this->once())
            ->method('make')
            ->with($handlerClass)
            ->willReturn($handler);

        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);
        $this->assertIsCallable($resolvedHandler);

        $result = $resolvedHandler($query);
        $this->assertEquals(['result' => 'test'], $result);
    }

    public function testResolveHandlerWithComplexReturn(): void
    {
        // Handler class that returns an object
        $handlerClass = get_class(new class {
            public function handle($query)
            {
                $result = new \stdClass();
                $result->id = 123;
                $result->name = 'Test';
                return $result;
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $query = new \stdClass();

        $this->container->expects($this->once())
            ->method('make')
            ->with($handlerClass)
            ->willReturn($handler);

        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);
        $result = $resolvedHandler($query);

        $this->assertIsObject($result);
        $this->assertEquals(123, $result->id);
        $this->assertEquals('Test', $result->name);
    }

    public function testResolveHandlerWithException(): void
    {
        // Handler class that throws an exception
        $handlerClass = get_class(new class {
            public function handle($query)
            {
                throw new \RuntimeException('Query handling failed');
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $query = new \stdClass();

        $this->container->expects($this->once())
            ->method('make')
            ->with($handlerClass)
            ->willReturn($handler);

        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query handling failed');

        $resolvedHandler($query);
    }

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->resolver = new QueryHandlerResolver($this->container);
    }
}