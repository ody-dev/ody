<?php

namespace Ody\CQRS\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case class with common functionality
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Create a new reflection property for testing protected/private properties
     *
     * @param string $class
     * @param string $property
     * @return \ReflectionProperty
     */
    protected function getReflectionProperty(string $class, string $property): \ReflectionProperty
    {
        $reflection = new \ReflectionClass($class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop;
    }

    /**
     * Call a protected/private method via reflection
     *
     * @param object $object
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    protected function callReflectionMethod(object $object, string $method, array $parameters = []): mixed
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Create a mock for a class with a dynamic method added
     *
     * @param string $className
     * @param string $methodName
     * @param mixed $returnValue
     * @return object
     */
    protected function createMockWithMethod(string $className, string $methodName, mixed $returnValue): object
    {
        return new class($className, $methodName, $returnValue) {
            private $className;
            private $methodName;
            private $returnValue;

            public function __construct(string $className, string $methodName, mixed $returnValue)
            {
                $this->className = $className;
                $this->methodName = $methodName;
                $this->returnValue = $returnValue;
            }

            public function __call(string $name, array $arguments)
            {
                if ($name === $this->methodName) {
                    return $this->returnValue;
                }

                throw new \BadMethodCallException("Method {$name} does not exist");
            }
        };
    }
}