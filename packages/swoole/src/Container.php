<?php

namespace Ody\Swoole;

class Container
{
    private $container = [];
    private $allowKeys = null;

    function __construct(array $allowKeys = null)
    {
        $this->allowKeys = $allowKeys;
    }

    function set($key, $item)
    {
        if(is_array($this->allowKeys) && !in_array($key,$this->allowKeys)){
            return false;
        }
        $this->container[$key] = $item;
        return $this;
    }

    function delete($key)
    {
        if(isset($this->container[$key])){
            unset($this->container[$key]);
        }
        return $this;
    }

    function get($key)
    {
        if(isset($this->container[$key])){
            return $this->container[$key];
        }else{
            return null;
        }
    }

    function clear()
    {
        $this->container = [];
    }

    function all():array
    {
        return $this->container;
    }
}