<?php

namespace Ody\Container\Tests;

use Ody\Container\Contracts\CircularDependencyException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

class CircularDependencyExceptionTest extends TestCase
{
    public function testIsInstanceOfContainerExceptionInterface()
    {
        $exception = new CircularDependencyException();

        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
    }

    public function testIsInstanceOfException()
    {
        $exception = new CircularDependencyException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testCanSetMessage()
    {
        $message = 'Circular dependency detected';
        $exception = new CircularDependencyException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCanSetCodeAndPrevious()
    {
        $previous = new \Exception('Previous');
        $exception = new CircularDependencyException('Message', 100, $previous);

        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}