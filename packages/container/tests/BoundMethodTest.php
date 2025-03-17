<?php

namespace Ody\Container\Tests;

use Ody\Container\BoundMethod;
use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use PHPUnit\Framework\TestCase;
use stdClass;

class BoundMethodTest extends TestCase
{
    public function testCallWithClosure()
    {
        $container = new Container();

        $result = BoundMethod::call($container, function () {
            return 'foo';
        });

        $this->assertEquals('foo', $result);
    }

    public function testCallWithDependencies()
    {
        $container = new Container();

        $result = BoundMethod::call($container, function (stdClass $stdClass, $foo = 'default') {
            return [$stdClass, $foo];
        });

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertEquals('default', $result[1]);
    }

    public function testCallWithNamedDependencies()
    {
        $container = new Container();

        $result = BoundMethod::call($container, function (stdClass $stdClass, $foo = 'default') {
            return [$stdClass, $foo];
        }, ['foo' => 'bar']);

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertEquals('bar', $result[1]);
    }

    public function testCallWithClassTypeNamedParameters()
    {
        $container = new Container();

        $instance = new stdClass();
        $instance->name = 'Taylor';

        $result = BoundMethod::call($container, function (stdClass $stdClass) {
            return $stdClass;
        }, [stdClass::class => $instance]);

        $this->assertSame($instance, $result);
    }

    public function testCallStringClass()
    {
        $container = new Container();

        $result = BoundMethod::call($container, TestCallClass::class.'@work');

        $this->assertEquals('foo', $result);
    }

    public function testCallStringClassWithDefaultMethod()
    {
        $container = new Container();

        $result = BoundMethod::call($container, TestCallClass::class, [], 'work');

        $this->assertEquals('foo', $result);
    }

    public function testCallStringClassWithParameters()
    {
        $container = new Container();

        $result = BoundMethod::call($container, TestCallClass::class.'@inject', ['default' => 'bar']);

        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallObjectMethod()
    {
        $container = new Container();
        $object = new TestCallClass();

        $result = BoundMethod::call($container, [$object, 'work']);

        $this->assertEquals('foo', $result);
    }

    public function testCallObjectMethodWithInvoke()
    {
        $container = new Container();
        $object = new TestCallableClass();

        $result = BoundMethod::call($container, $object);

        $this->assertEquals('invoked', $result);
    }

    public function testCallObjectMethodWithParameters()
    {
        $container = new Container();
        $object = new TestCallClass();

        $result = BoundMethod::call($container, [$object, 'inject'], ['default' => 'bar']);

        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallObjectStaticMethod()
    {
        $container = new Container();

        $result = BoundMethod::call($container, 'Ody\Container\Tests\TestCallClass::staticWork');

        $this->assertEquals('static foo', $result);
    }

    public function testCallClassWithoutMethodProvided()
    {
        $this->expectException(\InvalidArgumentException::class);

        $container = new Container();
        BoundMethod::call($container, TestCallClass::class);
    }

    public function testCallWithUnresolvablePrimitive()
    {
        $this->expectException(BindingResolutionException::class);

        $container = new Container();
        BoundMethod::call($container, function ($foo) {
            return $foo;
        });
    }

    public function testCallWithVariadicParameters()
    {
        $container = new Container();

        $result = BoundMethod::call($container, function (stdClass ...$foo) {
            return $foo;
        });

        $this->assertIsArray($result);
        $this->assertInstanceOf(stdClass::class, $result[0]);
    }

    public function testCallWithVariadicCustomParameters()
    {
        $container = new Container();

        $a = new stdClass();
        $a->name = 'A';
        $b = new stdClass();
        $b->name = 'B';

        $result = BoundMethod::call($container, function (...$foo) {
            return $foo;
        }, ['foo' => [$a, $b]]);

        $this->assertCount(1, $result);
        $this->assertSame($a, $result[0]);
        $this->assertSame($b, $result[1]);
    }
}

class TestCallableClass
{
    public function __invoke()
    {
        return 'invoked';
    }
}