<?php

namespace Ody\CQRS\Tests\Unit\Handler\Registry;

use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use PHPUnit\Framework\TestCase;

class QueryHandlerRegistryTest extends TestCase
{
    private $registry;

    public function testRegisterHandler(): void
    {
        $queryClass = 'TestQuery';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->registry->registerHandler($queryClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlerFor($queryClass));
        $this->assertEquals(
            ['class' => $handlerClass, 'method' => $handlerMethod],
            $this->registry->getHandlerFor($queryClass)
        );
    }

    public function testHasHandlerFor(): void
    {
        $queryClass = 'TestQuery';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertFalse($this->registry->hasHandlerFor($queryClass));

        $this->registry->registerHandler($queryClass, $handlerClass, $handlerMethod);

        $this->assertTrue($this->registry->hasHandlerFor($queryClass));
    }

    public function testGetHandlerFor(): void
    {
        $queryClass = 'TestQuery';
        $handlerClass = 'TestHandler';
        $handlerMethod = 'handle';

        $this->assertNull($this->registry->getHandlerFor($queryClass));

        $this->registry->registerHandler($queryClass, $handlerClass, $handlerMethod);

        $this->assertEquals(
            ['class' => $handlerClass, 'method' => $handlerMethod],
            $this->registry->getHandlerFor($queryClass)
        );
    }

    public function testGetHandlers(): void
    {
        $queryClass1 = 'TestQuery1';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';

        $queryClass2 = 'TestQuery2';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->assertEmpty($this->registry->getHandlers());

        $this->registry->registerHandler($queryClass1, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($queryClass2, $handlerClass2, $handlerMethod2);

        $expected = [
            $queryClass1 => ['class' => $handlerClass1, 'method' => $handlerMethod1],
            $queryClass2 => ['class' => $handlerClass2, 'method' => $handlerMethod2]
        ];

        $this->assertEquals($expected, $this->registry->getHandlers());
    }

    public function testRegisterOverwritesExistingHandler(): void
    {
        $queryClass = 'TestQuery';
        $handlerClass1 = 'TestHandler1';
        $handlerMethod1 = 'handle1';
        $handlerClass2 = 'TestHandler2';
        $handlerMethod2 = 'handle2';

        $this->registry->registerHandler($queryClass, $handlerClass1, $handlerMethod1);
        $this->registry->registerHandler($queryClass, $handlerClass2, $handlerMethod2);

        $this->assertEquals(
            ['class' => $handlerClass2, 'method' => $handlerMethod2],
            $this->registry->getHandlerFor($queryClass)
        );
    }

    protected function setUp(): void
    {
        $this->registry = new QueryHandlerRegistry();
    }
}