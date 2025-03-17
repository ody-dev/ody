<?php

namespace Ody\Container\Tests;

use Ody\Container\Contracts\BindingResolutionException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

class BindingResolutionExceptionTest extends TestCase
{
    public function testIsInstanceOfContainerExceptionInterface()
    {
        $exception = new BindingResolutionException();

        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
    }

    public function testIsInstanceOfException()
    {
        $exception = new BindingResolutionException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testCanSetMessage()
    {
        $message = 'Cannot resolve binding';
        $exception = new BindingResolutionException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCanSetCodeAndPrevious()
    {
        $previous = new \Exception('Previous');
        $exception = new BindingResolutionException('Message', 100, $previous);

        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}