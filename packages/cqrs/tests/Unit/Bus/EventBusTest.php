<?php

namespace Ody\CQRS\Tests\Unit\Bus;

use Ody\Container\Container;
use Ody\CQRS\Bus\EventBus;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EventBusTest extends TestCase
{
    private $handlerRegistry;
    private $container;
    private $middlewareProcessor;
    private $logger;
    private $eventBus;

    public function testPublishExecutesHandlers(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
            ['class' => 'Handler2', 'method' => 'handle']
        ];

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlersFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfos);

        $this->middlewareProcessor->expects($this->once())
            ->method('process')
            ->with(
                $this->eventBus,
                'executeHandlers',
                [$event, $handlerInfos],
                $this->isType('callable')
            );

        $this->eventBus->publish($event);
    }

    public function testPublishHandlesExceptions(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
        ];
        $expectedException = new \RuntimeException('Handler error');

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlersFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfos);

        $this->middlewareProcessor->expects($this->once())
            ->method('process')
            ->with(
                $this->eventBus,
                'executeHandlers',
                [$event, $handlerInfos],
                $this->isType('callable')
            )
            ->willThrowException($expectedException);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error publishing event'));

        // Should not throw exception
        $this->eventBus->publish($event);
    }

    public function testPublishWithoutMiddlewareProcessor(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
            ['class' => 'Handler2', 'method' => 'handle']
        ];

        $this->handlerRegistry->expects($this->once())
            ->method('getHandlersFor')
            ->with(\stdClass::class)
            ->willReturn($handlerInfos);

        $eventBus = new EventBus(
            $this->handlerRegistry,
            $this->container,
            $this->logger,
            null
        );

        // Create a mock for the EventBus that allows spying on executeHandlers
        $eventBusSpy = $this->getMockBuilder(EventBus::class)
            ->setConstructorArgs([$this->handlerRegistry, $this->container, $this->logger, null])
            ->onlyMethods(['executeHandlers'])
            ->getMock();

        $eventBusSpy->expects($this->once())
            ->method('executeHandlers')
            ->with($event, $handlerInfos);

        $eventBusSpy->publish($event);
    }

    public function testExecuteHandlersWithMiddlewareProcessor(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
            ['class' => 'Handler2', 'method' => 'handle']
        ];

        $handler1 = new \stdClass();
        $handler2 = new \stdClass();

        $this->container->expects($this->exactly(2))
            ->method('make')
            ->withConsecutive(
                ['Handler1'],
                ['Handler2']
            )
            ->willReturnOnConsecutiveCalls($handler1, $handler2);

        $this->middlewareProcessor->expects($this->exactly(2))
            ->method('process')
            ->withConsecutive(
                [$handler1, 'handle', [$event], $this->isType('callable')],
                [$handler2, 'handle', [$event], $this->isType('callable')]
            );

        $this->eventBus->executeHandlers($event, $handlerInfos);
    }

    public function testExecuteHandlersWithoutMiddlewareProcessor(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
        ];

        $handler = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['handle'])
            ->getMock();

        $handler->expects($this->once())
            ->method('handle')
            ->with($event);

        $this->container->expects($this->once())
            ->method('make')
            ->with('Handler1')
            ->willReturn($handler);

        $eventBus = new EventBus(
            $this->handlerRegistry,
            $this->container,
            $this->logger,
            null
        );

        $eventBus->executeHandlers($event, $handlerInfos);
    }

    public function testExecuteHandlersHandlesExceptions(): void
    {
        $event = new \stdClass();
        $handlerInfos = [
            ['class' => 'Handler1', 'method' => 'handle'],
            ['class' => 'Handler2', 'method' => 'handle']
        ];

        $expectedException = new \RuntimeException('Handler error');

        $handler1 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['handle'])
            ->getMock();

        $handler1->expects($this->once())
            ->method('handle')
            ->with($event)
            ->willThrowException($expectedException);

        $handler2 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['handle'])
            ->getMock();

        $handler2->expects($this->once())
            ->method('handle')
            ->with($event);

        $this->container->expects($this->exactly(2))
            ->method('make')
            ->withConsecutive(
                ['Handler1'],
                ['Handler2']
            )
            ->willReturnOnConsecutiveCalls($handler1, $handler2);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error handling event'));

        // Event handler exceptions should be caught and logged, but not propagated
        $eventBus = new EventBus(
            $this->handlerRegistry,
            $this->container,
            $this->logger,
            null
        );

        $eventBus->executeHandlers($event, $handlerInfos);
    }

    public function testAddMiddleware(): void
    {
        $middleware = new \stdClass();
        $result = $this->eventBus->addMiddleware($middleware);

        $this->assertSame($this->eventBus, $result);
//        $this->assertAttributeContains($middleware, 'middleware', $this->eventBus);
    }

    protected function setUp(): void
    {
        $this->handlerRegistry = $this->createMock(EventHandlerRegistry::class);
        $this->container = $this->createMock(Container::class);
        $this->middlewareProcessor = $this->createMock(MiddlewareProcessor::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->eventBus = new EventBus(
            $this->handlerRegistry,
            $this->container,
            $this->logger,
            $this->middlewareProcessor
        );
    }
}