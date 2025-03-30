<?php

namespace Ody\CQRS\Tests\Unit\Bus;

use Ody\CQRS\Bus\QueryBus;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use PHPUnit\Framework\TestCase;

class QueryBusTest extends TestCase
{
    private $handlerRegistry;
    private $handlerResolver;
    private $middlewareProcessor;
    private $queryBus;

    public function testDispatchThrowsExceptionWhenNoHandlerFound(): void
    {
        $query = new \stdClass();

        $this->handlerRegistry->expects($this->once())
            ->method('hasHandlerFor')
            ->with(\stdClass::class)
            ->willReturn(false);

        $this->expectException(HandlerNotFoundException::class);
        $this->queryBus->dispatch($query);
    }

    public function testDispatchReturnsResultSuccessfully(): void
    {
        $query = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];
        $expectedResult = ['id' => 1, 'name' => 'Test'];

        $this->handlerRegistry->expects($this->once())
            ->method('hasHandlerFor')
            ->with(\stdClass::class)
            ->willReturn(true);

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlerFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfo);

        $this->middlewareProcessor->expects($this->once())
            ->method('process')
            ->with(
                $this->queryBus,
                'executeHandler',
                [$query, $handlerInfo],
                $this->isType('callable')
            )
            ->willReturn($expectedResult);

        $result = $this->queryBus->dispatch($query);
        $this->assertSame($expectedResult, $result);
    }

    public function testDispatchWithoutMiddlewareProcessor(): void
    {
        $query = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];
        $expectedResult = ['id' => 1, 'name' => 'Test'];

        $this->handlerRegistry->expects($this->once())
            ->method('hasHandlerFor')
            ->with(\stdClass::class)
            ->willReturn(true);

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlerFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfo);

        // Create a partial mock for the QueryBus to spy on executeHandler
        $queryBusSpy = $this->getMockBuilder(QueryBus::class)
            ->setConstructorArgs([$this->handlerRegistry, $this->handlerResolver, null])
            ->onlyMethods(['executeHandler'])
            ->getMock();

        $queryBusSpy->expects($this->once())
            ->method('executeHandler')
            ->with($query, $handlerInfo)
            ->willReturn($expectedResult);

        $result = $queryBusSpy->dispatch($query);
        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteHandlerReturnsResultFromHandler(): void
    {
        $query = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];
        $expectedResult = ['id' => 1, 'name' => 'Test'];

        $handler = function () use ($expectedResult) {
            return $expectedResult;
        };

        $this->handlerResolver->expects($this->once())
            ->method('resolveHandler')
            ->with($handlerInfo)
            ->willReturn($handler);

        $result = $this->queryBus->executeHandler($query, $handlerInfo);
        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteHandlerPropagatesExceptions(): void
    {
        $query = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];
        $expectedException = new \RuntimeException('Handler error');

        $handler = function () use ($expectedException) {
            throw $expectedException;
        };

        $this->handlerResolver->expects($this->once())
            ->method('resolveHandler')
            ->with($handlerInfo)
            ->willReturn($handler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler error');

        $this->queryBus->executeHandler($query, $handlerInfo);
    }

    public function testAddMiddleware(): void
    {
        $middleware = new \stdClass();
        $result = $this->queryBus->addMiddleware($middleware);

        $this->assertSame($this->queryBus, $result);
//        $this->assertAttributeContains($middleware, 'middleware', $this->queryBus);
    }

    public function testGetHandlerRegistry(): void
    {
        $this->assertSame($this->handlerRegistry, $this->queryBus->getHandlerRegistry());
    }

    protected function setUp(): void
    {
        $this->handlerRegistry = $this->createMock(QueryHandlerRegistry::class);
        $this->handlerResolver = $this->createMock(QueryHandlerResolver::class);
        $this->middlewareProcessor = $this->createMock(MiddlewareProcessor::class);

        $this->queryBus = new QueryBus(
            $this->handlerRegistry,
            $this->handlerResolver,
            $this->middlewareProcessor
        );
    }
}