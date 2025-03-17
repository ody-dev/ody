<?php

namespace Ody\Container\Tests;

use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use Ody\Container\Contracts\CircularDependencyException;
use Ody\Container\EntryNotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    public function testBindBasic()
    {
        $container = new Container();
        $container->bind('foo', function () {
            return 'bar';
        });

        $this->assertTrue($container->bound('foo'));
        $this->assertEquals('bar', $container->make('foo'));
    }

    public function testBindShared()
    {
        $container = new Container();
        $container->singleton('foo', function () {
            return new stdClass();
        });

        $obj1 = $container->make('foo');
        $obj2 = $container->make('foo');

        $this->assertSame($obj1, $obj2);
    }

    public function testBindIf()
    {
        $container = new Container();
        $container->bindIf('foo', function () {
            return 'bar';
        });
        $this->assertEquals('bar', $container->make('foo'));

        // This binding should be ignored since foo already exists
        $container->bindIf('foo', function () {
            return 'baz';
        });
        $this->assertEquals('bar', $container->make('foo'));
    }

    public function testSingletonIf()
    {
        $container = new Container();
        $container->singletonIf('foo', function () {
            return new stdClass();
        });
        $obj1 = $container->make('foo');

        // This binding should be ignored since foo already exists
        $container->singletonIf('foo', function () {
            $obj = new stdClass();
            $obj->name = 'different';
            return $obj;
        });
        $obj2 = $container->make('foo');

        $this->assertSame($obj1, $obj2);
    }

    public function testResolving()
    {
        $container = new Container();

        // Flag to check if callbacks were called
        $callbacksRun = [];

        $container->resolving(function ($object, $container) use (&$callbacksRun) {
            $object->resolving = true;
            $callbacksRun['global_resolving'] = true;
        });

        $container->resolving(stdClass::class, function ($object, $container) use (&$callbacksRun) {
            $object->stdClassResolving = true;
            $callbacksRun['stdclass_resolving'] = true;
        });

        $container->afterResolving(function ($object, $container) use (&$callbacksRun) {
            $object->afterResolving = true;
            $callbacksRun['global_after_resolving'] = true;
        });

        $container->afterResolving(stdClass::class, function ($object, $container) use (&$callbacksRun) {
            $object->stdClassAfterResolving = true;
            $callbacksRun['stdclass_after_resolving'] = true;
        });

        // Create a simple, direct binding for the beforeResolving test
        $container->beforeResolving(function ($abstract, $parameters, $container) use (&$callbacksRun) {
            $callbacksRun['global_before_resolving'] = true;
        });

        $container->beforeResolving(stdClass::class, function ($abstract, $parameters, $container) use (&$callbacksRun) {
            $callbacksRun['stdclass_before_resolving'] = true;
        });

        // Make sure we're instantiating stdClass directly to avoid loops
        $instance = $container->make(stdClass::class);

        // Check that all properties were set on the instance
        $this->assertTrue($instance->resolving);
        $this->assertTrue($instance->stdClassResolving);
        $this->assertTrue($instance->afterResolving);
        $this->assertTrue($instance->stdClassAfterResolving);

        // Check that all callbacks ran
        $this->assertTrue($callbacksRun['global_resolving']);
        $this->assertTrue($callbacksRun['stdclass_resolving']);
        $this->assertTrue($callbacksRun['global_after_resolving']);
        $this->assertTrue($callbacksRun['stdclass_after_resolving']);
        $this->assertTrue($callbacksRun['global_before_resolving']);
        $this->assertTrue($callbacksRun['stdclass_before_resolving']);
    }

    public function testInstance()
    {
        $container = new Container();

        $obj = new stdClass();
        $obj->name = 'Taylor';

        $container->instance('foo', $obj);

        $this->assertTrue($container->bound('foo'));
        $this->assertSame($obj, $container->make('foo'));
    }

    public function testContextualBinding()
    {
        $container = new Container();

        // Define a default binding for StdClassInterface
        $container->bind(StdClassInterface::class, StdClassImplementation::class);

        // Define a contextual binding
        $container->when(ClassWithDependency::class)
            ->needs(StdClassInterface::class)
            ->give(function () {
                $obj = new StdClassImplementation();
                $obj->name = 'contextual';
                return $obj;
            });

        $instance = $container->make(ClassWithDependency::class);
        $this->assertEquals('contextual', $instance->dependency->name);

        // Test with non-class string dependency
        $container->when(ClassWithStringDependency::class)
            ->needs('$paramName')
            ->give('contextual value');

        $instance = $container->make(ClassWithStringDependency::class);
        $this->assertEquals('contextual value', $instance->paramValue);
    }

    public function testArrayAccess()
    {
        $container = new Container();

        $container['foo'] = function () {
            return 'bar';
        };

        $this->assertTrue(isset($container['foo']));
        $this->assertEquals('bar', $container['foo']);

        unset($container['foo']);
        $this->assertFalse(isset($container['foo']));
    }

    public function testAlias()
    {
        $container = new Container();

        $container->bind('foo', function () {
            return 'bar';
        });

        $container->alias('foo', 'baz');

        $this->assertTrue($container->isAlias('baz'));
        $this->assertEquals('bar', $container->make('baz'));
    }

    public function testAliasToSelf()
    {
        $this->expectException(\LogicException::class);

        $container = new Container();
        $container->alias('foo', 'foo');
    }

    public function testExtend()
    {
        $container = new Container();

        $container->bind('foo', function () {
            $obj = new stdClass();
            $obj->original = true;
            return $obj;
        });

        $container->extend('foo', function ($obj, $container) {
            $obj->extended = true;
            return $obj;
        });

        $result = $container->make('foo');

        $this->assertTrue($result->original);
        $this->assertTrue($result->extended);
    }

    public function testExtendInstance()
    {
        $container = new Container();

        $obj = new stdClass();
        $obj->original = true;

        $container->instance('foo', $obj);

        $container->extend('foo', function ($obj, $container) {
            $obj->extended = true;
            return $obj;
        });

        $result = $container->make('foo');

        $this->assertTrue($result->original);
        $this->assertTrue($result->extended);
        $this->assertSame($obj, $result);
    }

    public function testTagged()
    {
        $container = new Container();

        $container->bind('report1', function () {
            return 'report1 value';
        });

        $container->bind('report2', function () {
            return 'report2 value';
        });

        $container->tag(['report1', 'report2'], 'reports');

        $results = $container->tagged('reports');

        $results = iterator_to_array($results);

        $this->assertCount(2, $results);
        $this->assertEquals('report1 value', $results[0]);
        $this->assertEquals('report2 value', $results[1]);
    }

    public function testNestedDependencies()
    {
        $container = new Container();

        // Bind the interface to a concrete implementation first
        $container->bind(StdClassInterface::class, StdClassImplementation::class);

        $instance = $container->make(ClassWithNestedDependency::class);

        $this->assertInstanceOf(StdClassInterface::class, $instance->dependency->dependency);
    }

    public function testAutomaticInjection()
    {
        $container = new Container();

        // Bind the interface to a concrete implementation first
        $container->bind(StdClassInterface::class, StdClassImplementation::class);

        $instance = $container->make(ClassWithDependency::class);

        $this->assertInstanceOf(StdClassInterface::class, $instance->dependency);
    }

    public function testUnresolvablePrimitive()
    {
        $this->expectException(BindingResolutionException::class);

        $container = new Container();
        $container->make(ClassWithUnresolvablePrimitive::class);
    }

//    public function testCircularDependency()
//    {
//        $this->expectException(\Ody\Container\Contracts\BindingResolutionException::class);
//
//        // Create a new container to avoid side effects
//        $container = new Container();
//
//        // Set a maximum recursion depth to prevent PHP from running out of memory
//        $container->when(CircularClassA::class)
//            ->needs(CircularClassB::class)
//            ->give(function($container) {
//                return $container->make(CircularClassB::class);
//            });
//
//        $container->when(CircularClassB::class)
//            ->needs(CircularClassA::class)
//            ->give(function($container) {
//                return $container->make(CircularClassA::class);
//            });
//
//        // This should throw a BindingResolutionException due to the circular dependency
//        $container->make(CircularClassA::class);
//    }

    public function testCallWithoutMethodBinding()
    {
        $container = new Container();

        $result = $container->call(function (stdClass $obj) {
            $obj->called = true;
            return $obj;
        });

        $this->assertTrue($result->called);
    }

    public function testCallWithMethodBinding()
    {
        $container = new Container();

        // Create a real class to bind the method to
        $container->bindMethod(TestCallableClass::class.'@work', function ($instance, $container) {
            return 'method bound';
        });

        $result = $container->call(TestCallableClass::class.'@work');

        $this->assertEquals('method bound', $result);
    }

    public function testCallWithClassAtMethod()
    {
        $container = new Container();

        $result = $container->call(TestCallClass::class.'@method');

        $this->assertEquals('method called', $result);
    }

    public function testCallWithArrayCallback()
    {
        $container = new Container();

        $instance = new TestCallClass();
        $result = $container->call([$instance, 'method']);

        $this->assertEquals('method called', $result);
    }

    public function testCallWithParameters()
    {
        $container = new Container();

        $result = $container->call(function ($param) {
            return $param;
        }, ['param' => 'value']);

        $this->assertEquals('value', $result);
    }

    public function testGetWithUnknownClass()
    {
        $this->expectException(EntryNotFoundException::class);

        $container = new Container();
        $container->get('NotExistClass');
    }

    public function testWrap()
    {
        $container = new Container();

        $callback = function (stdClass $obj) {
            return $obj;
        };

        $wrapped = $container->wrap($callback);
        $result = $wrapped();

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testFactory()
    {
        $container = new Container();

        $container->bind('foo', function () {
            $obj = new stdClass();
            $obj->name = 'foo';
            return $obj;
        });

        $factory = $container->factory('foo');
        $result = $factory();

        $this->assertEquals('foo', $result->name);
    }

    public function testFlush()
    {
        $container = new Container();

        $container->bind('foo', function () {
            return 'bar';
        });

        $container->make('foo');

        $container->flush();

        $this->assertFalse($container->bound('foo'));
    }

    public function testRebinding()
    {
        $container = new Container();

        $container->bind('foo', function () {
            return new stdClass();
        });

        $called = false;

        $rebind = function () use (&$called) {
            $called = true;
        };

        $container->rebinding('foo', $rebind);

        // Initial binding won't trigger rebind
        $container->make('foo');
        $this->assertFalse($called);

        // But rebinding will
        $container->bind('foo', function () {
            $obj = new stdClass();
            $obj->name = 'different';
            return $obj;
        });

        $this->assertTrue($called);
    }

    public function testRefresh()
    {
        $container = new Container();

        $container->bind('foo', function () {
            return new stdClass();
        });

        $object = new ClassWithRefreshMethod();
        $container->refresh('foo', $object, 'handle');

        // Initial binding won't trigger refresh
        $firstInstance = $container->make('foo');
        $this->assertNull($object->instance);

        // But rebinding will
        $container->bind('foo', function () {
            $obj = new stdClass();
            $obj->name = 'different';
            return $obj;
        });

        $this->assertSame($container->make('foo'), $object->instance);
    }

    public function testForgetInstance()
    {
        $container = new Container();

        $container->singleton('foo', function () {
            return new stdClass();
        });

        $instance1 = $container->make('foo');

        $container->forgetInstance('foo');

        $instance2 = $container->make('foo');

        $this->assertNotSame($instance1, $instance2);
    }

    public function testForgetInstances()
    {
        $container = new Container();

        $container->singleton('foo', function () {
            return new stdClass();
        });

        $container->singleton('bar', function () {
            return new stdClass();
        });

        $foo1 = $container->make('foo');
        $bar1 = $container->make('bar');

        $container->forgetInstances();

        $foo2 = $container->make('foo');
        $bar2 = $container->make('bar');

        $this->assertNotSame($foo1, $foo2);
        $this->assertNotSame($bar1, $bar2);
    }

    public function testScoped()
    {
        $container = new Container();

        $container->scoped('foo', function () {
            return new stdClass();
        });

        $instance1 = $container->make('foo');
        $instance2 = $container->make('foo');

        $this->assertSame($instance1, $instance2);

        $container->forgetScopedInstances();

        $instance3 = $container->make('foo');

        $this->assertNotSame($instance1, $instance3);
    }

    public function testScopedIf()
    {
        $container = new Container();

        $container->scopedIf('foo', function () {
            return new stdClass();
        });

        $instance1 = $container->make('foo');

        $container->scopedIf('foo', function () {
            $obj = new stdClass();
            $obj->name = 'different';
            return $obj;
        });

        $instance2 = $container->make('foo');

        $this->assertSame($instance1, $instance2);
    }

    public function testGlobalInstance()
    {
        Container::setInstance(null);

        $container = Container::getInstance();

        $this->assertInstanceOf(Container::class, $container);

        $container2 = Container::getInstance();

        $this->assertSame($container, $container2);

        $newContainer = new Container();
        Container::setInstance($newContainer);

        $this->assertSame($newContainer, Container::getInstance());
    }

    public function testMagicMethods()
    {
        $container = new Container();

        $container->bind('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $container->foo);

        $container->foo = 'baz';

        $this->assertEquals('baz', $container->make('foo'));
    }

    public function testVariadicDependencies()
    {
        $container = new Container();

        $container->bind('reports', function () {
            return [
                new stdClass(),
                new stdClass(),
                new stdClass(),
            ];
        });

        $container->when(ClassWithVariadicDependency::class)
            ->needs(stdClass::class)
            ->give('reports');

        $instance = $container->make(ClassWithVariadicDependency::class);

        $this->assertCount(3, $instance->reports);
    }

    public function testNotInstantiable()
    {
        $this->expectException(BindingResolutionException::class);

        $container = new Container();
        $container->make(StdClassInterface::class);
    }

    public function testMakeWithCallbackReturningSelf()
    {
        $container = new Container();

        $obj = new stdClass();
        $obj->name = 'Taylor';

        $container->bind('foo', function ($app) use ($obj) {
            return $obj;
        });

        $this->assertSame($obj, $container->make('foo'));
    }
}

//---- Test classes below ---

interface StdClassInterface {}

class StdClassImplementation implements StdClassInterface {}

class ClassWithDependency
{
    public $dependency;

    public function __construct(StdClassInterface $dependency)
    {
        $this->dependency = $dependency;
    }
}

class ClassWithStringDependency
{
    public $paramValue;

    public function __construct(string $paramName)
    {
        $this->paramValue = $paramName;
    }
}

class ClassWithNestedDependency
{
    public $dependency;

    public function __construct(ClassWithDependency $dependency)
    {
        $this->dependency = $dependency;
    }
}

class ClassWithUnresolvablePrimitive
{
    public function __construct(string $param)
    {
        // This should fail without a binding
    }
}

class CircularClassA
{
    public $b;

    public function __construct(CircularClassB $b)
    {
        $this->b = $b;
    }
}

class CircularClassB
{
    public $a;

    public function __construct(CircularClassA $a)
    {
        $this->a = $a;
    }
}

class ClassWithRefreshMethod
{
    public $instance;

    public function handle($instance)
    {
        $this->instance = $instance;
    }
}

class ClassWithVariadicDependency
{
    public $reports;

    public function __construct(stdClass ...$reports)
    {
        $this->reports = $reports;
    }
}