<?php

namespace Ody\CQRS\Tests\Unit\Bus;

use Ody\CQRS\Bus\CommandBus;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use PHPUnit\Framework\TestCase;

class CommandBusTest extends TestCase
{
    private $handlerRegistry;
    private $handlerResolver;
    private $middlewareProcessor;
    private $commandBus;

    public function testDispatchThrowsExceptionWhenNoHandlerFound(): void
    {
        $command = new \stdClass();

        $this->handlerRegistry->expects($this->once())
            ->method('hasHandlerFor')
            ->with(\stdClass::class)
            ->willReturn(false);

        $this->expectException(HandlerNotFoundException::class);
        $this->commandBus->dispatch($command);
    }

    public function testDispatchExecutesHandlerSuccessfully(): void
    {
        $command = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];

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
                $this->commandBus,
                'executeHandler',
                [$command, $handlerInfo],
                $this->isType('callable')
            );

        $this->commandBus->dispatch($command);
    }

    public function testDispatchWithoutMiddlewareProcessor(): void
    {
        $command = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];

        $this->handlerRegistry->expects($this->once())
            ->method('hasHandlerFor')
            ->with(\stdClass::class)
            ->willReturn(true);

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlerFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfo);

        $commandBus = new CommandBus(
            $this->handlerRegistry,
            $this->handlerResolver
        );

        // Create a mock for the CommandBus that allows spying on executeHandler
        $commandBusSpy = $this->getMockBuilder(CommandBus::class)
            ->setConstructorArgs([$this->handlerRegistry, $this->handlerResolver])
            ->onlyMethods(['executeHandler'])
            ->getMock();

        $commandBusSpy->expects($this->once())
            ->method('executeHandler')
            ->with($command, $handlerInfo);

        $commandBusSpy->dispatch($command);
    }

    public function testExecuteHandlerCallsResolverAndInvokesHandler(): void
    {
        $command = new \stdClass();
        $handlerInfo = ['class' => 'TestHandler', 'method' => 'handle'];
        $handlerCallable = function () {
        };

        $this->handlerResolver->expects($this->once())
            ->method('resolveHandler')
            ->with($handlerInfo)
            ->willReturn($handlerCallable);

        // Create a mock for the handler callable
        $handler = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $handler->expects($this->once())
            ->method('__invoke')
            ->with($command);

        $this->handlerResolver = $this->createMock(CommandHandlerResolver::class);
        $this->handlerResolver->method('resolveHandler')->willReturn([$handler, '__invoke']);

        $commandBus = new CommandBus(
            $this->handlerRegistry,
            $this->handlerResolver
        );

        $commandBus->executeHandler($command, $handlerInfo);
    }

    public function testAddMiddleware(): void
    {
        $middleware = new \stdClass();
        $result = $this->commandBus->addMiddleware($middleware);

        $this->assertSame($this->commandBus, $result);
//        $this->assertAttributeContains($middleware, 'middleware', $this->commandBus);
    }

    protected function setUp(): void
    {
        $this->handlerRegistry = $this->createMock(CommandHandlerRegistry::class);
        $this->handlerResolver = $this->createMock(CommandHandlerResolver::class);
        $this->middlewareProcessor = $this->createMock(MiddlewareProcessor::class);

        $this->commandBus = new CommandBus(
            $this->handlerRegistry,
            $this->handlerResolver,
            $this->middlewareProcessor
        );
    }
}