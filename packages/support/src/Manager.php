<?php

namespace Ody\Support;

use Closure;
use Ody\Container\Container;

abstract class Manager
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The configuration repository instance.
     *
     * @var \Ody\Support\Config
     */
    protected $config;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param  Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    abstract public function getDefaultDriver();

    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return mixed
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new \InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        // If the given driver has not been created before, we will create the instance
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function createDriver($driver)
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create'.ucfirst($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new \InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->container);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @param  Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return array
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param  Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}