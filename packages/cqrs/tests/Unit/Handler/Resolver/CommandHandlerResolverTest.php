<?php

namespace ODY\CQRS\Tests\Unit\Handler\Resolver;

use Ody\Container\Container;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Interfaces\EventBusInterface;
use PHPUnit\Framework\TestCase;

class CommandHandlerResolverTest extends TestCase
{
    private $container;
    private $resolver;

    public function testResolveHandlerWithoutEventBus(): void
    {
        // Handler class that doesn't need an EventBus
        $handlerClass = get_class(new class {
            public function handle($command)
            {
                return 'handled';
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $command = new \stdClass();

        $this->container->expects($this->once())
            ->method('make')
            ->with($handlerClass)
            ->willReturn($handler);

        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);
        $this->assertIsCallable($resolvedHandler);

        $result = $resolvedHandler($command);
        $this->assertEquals('handled', $result);
    }

    public function testResolveHandlerWithEventBus(): void
    {
        // Create a handler class that expects an EventBus
        $handlerClass = get_class(new class {
            public function handle($command, EventBusInterface $eventBus)
            {
                return 'handled with event bus: ' . get_class($eventBus);
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $command = new \stdClass();
        $eventBus = $this->createMock(EventBusInterface::class);

        $this->container->expects($this->exactly(2))
            ->method('make')
            ->withConsecutive(
                [$handlerClass],
                [EventBusInterface::class]
            )
            ->willReturnOnConsecutiveCalls($handler, $eventBus);

        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);
        $this->assertIsCallable($resolvedHandler);

        $result = $resolvedHandler($command);
        $this->assertStringContainsString('handled with event bus:', $result);
    }

    public function testResolveHandlerWithOtherSecondParameter(): void
    {
        // Create a handler class that expects a different second parameter
        $handlerClass = get_class(new class {
            public function handle($command, \stdClass $other)
            {
                return 'handled with other param';
            }
        });

        $handlerInfo = [
            'class' => $handlerClass,
            'method' => 'handle'
        ];

        $handler = new $handlerClass();
        $command = new \stdClass();

        $this->container->expects($this->once())
            ->method('make')
            ->with($handlerClass)
            ->willReturn($handler);

        // This should not attempt to inject EventBus
        $resolvedHandler = $this->resolver->resolveHandler($handlerInfo);

        // This will fail because we're not providing the second parameter,
        // but we're just testing that it doesn't try to inject EventBus
        $this->expectException(\ArgumentCountError::class);
        $resolvedHandler($command);
    }

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->resolver = new CommandHandlerResolver($this->container);
    }
}