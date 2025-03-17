<?php

namespace Ody\Container\Tests;

use Ody\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base TestCase for Ody Container tests
 *
 * This class provides common functionality for testing
 * the Ody Container components
 */
class TestCase extends BaseTestCase
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
    }

    /**
     * Clean up the test environment
     */
    protected function tearDown(): void
    {
        $this->container = null;

        parent::tearDown();
    }

    /**
     * Create a fresh container instance
     *
     * @return Container
     */
    protected function freshContainer(): Container
    {
        return new Container();
    }

    /**
     * Assert that a given abstract type is bound in the container
     *
     * @param string $abstract The abstract type to check
     * @param Container|null $container Optional container to check instead of the default one
     */
    protected function assertBound(string $abstract, ?Container $container = null): void
    {
        $container = $container ?? $this->container;
        $this->assertTrue($container->bound($abstract), "Failed asserting that '{$abstract}' is bound in the container.");
    }

    /**
     * Assert that a given abstract type is not bound in the container
     *
     * @param string $abstract The abstract type to check
     * @param Container|null $container Optional container to check instead of the default one
     */
    protected function assertNotBound(string $abstract, ?Container $container = null): void
    {
        $container = $container ?? $this->container;
        $this->assertFalse($container->bound($abstract), "Failed asserting that '{$abstract}' is not bound in the container.");
    }

    /**
     * Assert that a given abstract type is resolved as a shared instance
     *
     * @param string $abstract The abstract type to check
     * @param Container|null $container Optional container to check instead of the default one
     */
    protected function assertShared(string $abstract, ?Container $container = null): void
    {
        $container = $container ?? $this->container;
        $instance1 = $container->make($abstract);
        $instance2 = $container->make($abstract);

        $this->assertSame(
            $instance1,
            $instance2,
            "Failed asserting that '{$abstract}' resolves to a shared instance."
        );
    }

    /**
     * Assert that a given abstract type is not resolved as a shared instance
     *
     * @param string $abstract The abstract type to check
     * @param Container|null $container Optional container to check instead of the default one
     */
    protected function assertNotShared(string $abstract, ?Container $container = null): void
    {
        $container = $container ?? $this->container;
        $instance1 = $container->make($abstract);
        $instance2 = $container->make($abstract);

        $this->assertNotSame(
            $instance1,
            $instance2,
            "Failed asserting that '{$abstract}' does not resolve to a shared instance."
        );
    }

    /**
     * Assert that a given alias resolves to the expected abstract type
     *
     * @param string $alias The alias to check
     * @param string $abstract The expected abstract type
     * @param Container|null $container Optional container to check instead of the default one
     */
    protected function assertAliasOf(string $alias, string $abstract, ?Container $container = null): void
    {
        $container = $container ?? $this->container;
        $this->assertTrue($container->isAlias($alias), "Failed asserting that '{$alias}' is an alias.");
        $this->assertEquals($abstract, $container->getAlias($alias), "Failed asserting that '{$alias}' is an alias of '{$abstract}'.");
    }

    /**
     * Create a mock object that can be used for testing
     *
     * @param string $class The class to mock
     * @param array $methods The methods to mock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createMock($class, array $methods = [])
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Create a mock instance of a class and register it in the container
     *
     * @param string $abstract The abstract type to bind
     * @param string $concrete The concrete class to mock
     * @param array $methods The methods to mock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function mockAndBind(string $abstract, string $concrete, array $methods = [])
    {
        $mock = $this->createMock($concrete, $methods);
        $this->container->instance($abstract, $mock);

        return $mock;
    }

    /**
     * Capture the output of a closure
     *
     * @param \Closure $closure The closure to execute
     * @return string The captured output
     */
    protected function captureOutput(\Closure $closure): string
    {
        ob_start();
        $closure();
        return ob_get_clean();
    }
}