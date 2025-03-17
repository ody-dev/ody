<?php

namespace Ody\Container\Tests;

class TestCallClass
{
    public function method()
    {
        return 'method called';
    }

    public function inject($default = 'foo')
    {
        return ['foo', $default];
    }

    public static function staticWork()
    {
        return 'static foo';
    }

    public function work()
    {
        return 'foo';
    }
}