<?php

namespace Ody\Container\Tests;

use Ody\Container\Util;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class UtilTest extends TestCase
{
    public function testArrayWrapWithArray()
    {
        $array = ['foo', 'bar'];
        $result = Util::arrayWrap($array);

        $this->assertSame($array, $result);
    }

    public function testArrayWrapWithString()
    {
        $string = 'foo';
        $result = Util::arrayWrap($string);

        $this->assertEquals(['foo'], $result);
    }

    public function testArrayWrapWithNull()
    {
        $result = Util::arrayWrap(null);

        $this->assertEquals([], $result);
    }

    public function testArrayWrapWithInteger()
    {
        $result = Util::arrayWrap(123);

        $this->assertEquals([123], $result);
    }

    public function testArrayWrapWithObject()
    {
        $object = new \stdClass();
        $result = Util::arrayWrap($object);

        $this->assertEquals([$object], $result);
    }

    public function testUnwrapIfClosureWithClosure()
    {
        $value = 'foo';
        $closure = function () use ($value) {
            return $value;
        };

        $result = Util::unwrapIfClosure($closure);

        $this->assertEquals('foo', $result);
    }

    public function testUnwrapIfClosureWithNonClosure()
    {
        $value = 'foo';
        $result = Util::unwrapIfClosure($value);

        $this->assertEquals('foo', $result);
    }

    public function testGetParameterClassNameWithClass()
    {
        $function = function (TestClass $param) {};
        $reflectionFunction = new ReflectionFunction($function);
        $reflectionParameter = $reflectionFunction->getParameters()[0];

        $result = Util::getParameterClassName($reflectionParameter);

        $this->assertEquals(TestClass::class, $result);
    }

    public function testGetParameterClassNameWithBuiltInType()
    {
        $function = function (string $param) {};
        $reflectionFunction = new ReflectionFunction($function);
        $reflectionParameter = $reflectionFunction->getParameters()[0];

        $result = Util::getParameterClassName($reflectionParameter);

        $this->assertNull($result);
    }

    public function testGetParameterClassNameWithSelf()
    {
        $object = new class {
            public function method(self $param) {}
        };

        $reflectionMethod = new ReflectionMethod($object, 'method');
        $reflectionParameter = $reflectionMethod->getParameters()[0];

        $result = Util::getParameterClassName($reflectionParameter);

        $this->assertEquals(get_class($object), $result);
    }

//    public function testGetParameterClassNameWithParent()
//    {
//        $parentClass = new class {};
//
//        $childClass = new class extends $parentClass {
//            public function method(parent $param) {}
//        };
//
//        $reflectionMethod = new ReflectionMethod($childClass, 'method');
//        $reflectionParameter = $reflectionMethod->getParameters()[0];
//
//        $result = Util::getParameterClassName($reflectionParameter);
//
//        $this->assertEquals(get_class($parentClass), $result);
//    }

    public function testGetParameterClassNameWithNoTypeHint()
    {
        $function = function ($param) {};
        $reflectionFunction = new ReflectionFunction($function);
        $reflectionParameter = $reflectionFunction->getParameters()[0];

        $result = Util::getParameterClassName($reflectionParameter);

        $this->assertNull($result);
    }
}

class TestClass {}