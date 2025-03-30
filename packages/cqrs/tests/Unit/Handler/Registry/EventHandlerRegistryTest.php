<?php

namespace Ody\CQRS\Tests\Unit\Handler\Registry;

use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use PHPUnit\Framework\TestCase;

class EventHandlerRegistryTest extends TestCase
{
    private $registry;

    public function testRegisterHandler(): void
    {
        $eventClass = 'TestEvent';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->registry->registerHandler($eventClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlersFor($eventClass));
        $this->assertCount(1, $this->registry->getHandlersFor($eventClass));
        $this->assertEquals(
            ['class' => $handlerClass, 'method' => $handlerMethod],
            $this->registry->getHandlersFor($eventClass)[0]
        );
    }

    public function testRegisterMultipleHandlersForSameEvent(): void
    {
        $eventClass = 'TestEvent';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->registry->registerHandler($eventClass, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($eventClass, $handlerClass2, $handlerMethod2);

        $this->assertTrue($this->registry->hasHandlersFor($eventClass));
        $this->assertCount(2, $this->registry->getHandlersFor($eventClass));

        $handlers = $this->registry->getHandlersFor($eventClass);
        $this->assertEquals(['class' => $handlerClass1, 'method' => $handlerMethod1], $handlers[0]);
        $this->assertEquals(['class' => $handlerClass2, 'method' => $handlerMethod2], $handlers[1]);
    }

    public function testHasHandlersFor(): void
    {
        $eventClass = 'TestEvent';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertFalse($this->registry->hasHandlersFor($eventClass));

        $this->registry->registerHandler($eventClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlersFor($eventClass));
    }

    public function testGetHandlersFor(): void
    {
        $eventClass = 'TestEvent';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertEmpty($this->registry->getHandlersFor($eventClass));

        $this->registry->registerHandler($eventClass, $handlerClass, $handlerMethod);

        $this->assertEquals(
            [['class' => $handlerClass, 'method' => $handlerMethod]],
            $this->registry->getHandlersFor($eventClass)
        );
    }

    public function testGetHandlers(): void
    {
        $eventClass1 = 'TestEvent1';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';

        $eventClass2 = 'TestEvent2';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->assertEmpty($this->registry->getHandlers());

        $this->registry->registerHandler($eventClass1, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($eventClass2, $handlerClass2, $handlerMethod2);

        $expected = [
            $eventClass1 => [['class' => $handlerClass1, 'method' => $handlerMethod1]],
            $eventClass2 => [['class' => $handlerClass2, 'method' => $handlerMethod2]]
        ];

        $this->assertEquals($expected, $this->registry->getHandlers());
    }

    public function testRegisterMultipleHandlersAndEvents(): void
    {
        $eventClass1 = 'TestEvent1';
        $handlerClass1a = 'TestHandler1A';
        $handlerMethod1a = 'handle1A';
        $handlerClass1b = 'TestHandler1B';
        $handlerMethod1b = 'handle1B';

        $eventClass2 = 'TestEvent2';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->registry->registerHandler($eventClass1, $handlerClass1a, $handlerMethod1a);
        $this->registry->registerHandler($eventClass1, $handlerClass1b, $handlerMethod1b);
        $this->registry->registerHandler($eventClass2, $handlerClass2, $handlerMethod2);

        $expected = [
            $eventClass1 => [
                ['class' => $handlerClass1a, 'method' => $handlerMethod1a],
                ['class' => $handlerClass1b, 'method' => $handlerMethod1b]
            ],
            $eventClass2 => [
                ['class' => $handlerClass2, 'method' => $handlerMethod2]
            ]
        ];

        $this->assertEquals($expected, $this->registry->getHandlers());
    }

    protected function setUp(): void
    {
        $this->registry = new EventHandlerRegistry();
    }
}