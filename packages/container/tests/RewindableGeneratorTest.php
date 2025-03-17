<?php

namespace Ody\Container\Tests;

use Ody\Container\RewindableGenerator;
use PHPUnit\Framework\TestCase;

class RewindableGeneratorTest extends TestCase
{
    public function testCanGetIteratorAndCount()
    {
        // Setup with fixed count
        $data = ['foo', 'bar', 'baz'];
        $generator = new RewindableGenerator(function () use ($data) {
            foreach ($data as $item) {
                yield $item;
            }
        }, count($data));

        // Test iteration
        $results = [];
        foreach ($generator as $value) {
            $results[] = $value;
        }

        $this->assertEquals($data, $results);
        $this->assertCount(3, $generator);
    }

    public function testCanRewind()
    {
        $data = ['foo', 'bar'];
        $generator = new RewindableGenerator(function () use ($data) {
            foreach ($data as $item) {
                yield $item;
            }
        }, count($data));

        // First iteration
        $results1 = [];
        foreach ($generator as $value) {
            $results1[] = $value;
        }

        // Second iteration
        $results2 = [];
        foreach ($generator as $value) {
            $results2[] = $value;
        }

        $this->assertEquals($results1, $results2);
    }

    public function testCallableCount()
    {
        $countCalled = 0;

        $generator = new RewindableGenerator(function () {
            yield 'foo';
            yield 'bar';
        }, function () use (&$countCalled) {
            $countCalled++;
            return 2;
        });

        $this->assertEquals(2, count($generator));
        $this->assertEquals(1, $countCalled);

        // Count should be cached after first call
        $this->assertEquals(2, count($generator));
        $this->assertEquals(1, $countCalled);
    }

    public function testEmptyGenerator()
    {
        $generator = new RewindableGenerator(function () {
            return;
            yield;
        }, 0);

        $results = [];
        foreach ($generator as $value) {
            $results[] = $value;
        }

        $this->assertEmpty($results);
        $this->assertCount(0, $generator);
    }

    public function testGeneratorWithKeys()
    {
        $data = ['foo' => 'bar', 'baz' => 'qux'];
        $generator = new RewindableGenerator(function () use ($data) {
            foreach ($data as $key => $value) {
                yield $key => $value;
            }
        }, count($data));

        $results = [];
        foreach ($generator as $key => $value) {
            $results[$key] = $value;
        }

        $this->assertEquals($data, $results);
    }
}