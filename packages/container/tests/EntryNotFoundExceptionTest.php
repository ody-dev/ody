<?php

namespace Ody\Container\Tests;

use Ody\Container\EntryNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

class EntryNotFoundExceptionTest extends TestCase
{
    public function testIsInstanceOfNotFoundExceptionInterface()
    {
        $exception = new EntryNotFoundException();

        $this->assertInstanceOf(NotFoundExceptionInterface::class, $exception);
    }

    public function testIsInstanceOfException()
    {
        $exception = new EntryNotFoundException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testCanSetMessage()
    {
        $message = 'Entry not found: foo';
        $exception = new EntryNotFoundException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCanSetCodeAndPrevious()
    {
        $previous = new \Exception('Previous');
        $exception = new EntryNotFoundException('Message', 100, $previous);

        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}