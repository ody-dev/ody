<?php

namespace Ody\CQRS\Tests\Unit\Handler\Registry;

use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use PHPUnit\Framework\TestCase;

class CommandHandlerRegistryTest extends TestCase
{
    private $registry;

    public function testRegisterHandler(): void
    {
        $commandClass = 'TestCommand';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->registry->registerHandler($commandClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlerFor($commandClass));
        $this->assertEquals(
            ['class' => $handlerClass, 'method' => $handlerMethod],
            $this->registry->getHandlerFor($commandClass)
        );
    }

    public function testHasHandlerFor(): void
    {
        $commandClass = 'TestCommand';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertFalse($this->registry->hasHandlerFor($commandClass));

        $this->registry->registerHandler($commandClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlerFor($commandClass));
    }

    public function testGetHandlerFor(): void
    {
        $commandClass = 'TestCommand';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertNull($this->registry->getHandlerFor($commandClass));

        $this->registry->registerHandler($commandClass, $handlerClass, $handlerMethod);

        $this->assertEquals(
            ['class' => $handlerClass, 'method' => $handlerMethod],
            $this->registry->getHandlerFor($commandClass)
        );
    }

    public function testGetHandlers(): void
    {
        $commandClass1 = 'TestCommand1';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';

        $commandClass2 = 'TestCommand2';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->assertEmpty($this->registry->getHandlers());

        $this->registry->registerHandler($commandClass1, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($commandClass2, $handlerClass2, $handlerMethod2);

        $expected = [
            $commandClass1 => ['class' => $handlerClass1, 'method' => $handlerMethod1],
            $commandClass2 => ['class' => $handlerClass2, 'method' => $handlerMethod2]
        ];

        $this->assertEquals($expected, $this->registry->getHandlers());
    }

    public function testRegisterOverwritesExistingHandler(): void
    {
        $commandClass = 'TestCommand';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->registry->registerHandler($commandClass, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($commandClass, $handlerClass2, $handlerMethod2);

        $this->assertEquals(
            ['class' => $handlerClass2, 'method' => $handlerMethod2],
            $this->registry->getHandlerFor($commandClass)
        );
    }

    protected function setUp(): void
    {
        $this->registry = new CommandHandlerRegistry();
    }
}