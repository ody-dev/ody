<?php

namespace Ody\Auth\Tests;

use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Swoole\Runtime;

/**
 * Base TestCase for Ody Framework tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Indicates if Swoole coroutine support is enabled in the testing environment
     *
     * @var bool
     */
    protected static $coroutinesEnabled = false;

    /**
     * Setup before the test class is instantiated
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Enable Swoole coroutine support for all tests if not already enabled
        if (!self::$coroutinesEnabled && extension_loaded('swoole')) {
            // Enable coroutine runtime hooks
            Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
            self::$coroutinesEnabled = true;
        }
    }

    /**
     * Setup before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Tear down after each test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up Mockery
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        parent::tearDown();
    }

    /**
     * Tear down after the test class is finished
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    /**
     * Skip the test if a condition is true
     *
     * @param bool $condition Condition to evaluate
     * @param string $message Message to display if skipped
     * @return void
     */
    protected function skipIf(bool $condition, string $message = ''): void
    {
        if ($condition) {
            $this->markTestSkipped($message);
        }
    }

    /**
     * Skip the test if Swoole is not installed
     *
     * @param string $message Message to display if skipped
     * @return void
     */
    protected function skipIfNoSwoole(string $message = 'Swoole extension is not available'): void
    {
        $this->skipIf(!extension_loaded('swoole'), $message);
    }

    /**
     * Skip the test if coroutines are not enabled
     *
     * @param string $message Message to display if skipped
     * @return void
     */
    protected function skipIfNoCoroutines(string $message = 'Swoole coroutine support is not enabled'): void
    {
        $this->skipIf(!self::$coroutinesEnabled, $message);
    }

    /**
     * Run a callback in a coroutine and return its result
     *
     * @param callable $callback Callback to run in a coroutine
     * @return mixed Result of the callback
     */
    protected function runInCoroutine(callable $callback)
    {
        $this->skipIfNoSwoole();

        $channel = new \Swoole\Coroutine\Channel(1);

        \Swoole\Coroutine\run(function () use ($callback, $channel) {
            try {
                $result = $callback();
                $channel->push(['result' => $result]);
            } catch (\Exception $e) {
                $channel->push(['exception' => $e]);
            }
        });

        $result = $channel->pop();

        if (isset($result['exception'])) {
            throw $result['exception'];
        }

        return $result['result'] ?? null;
    }

    /**
     * Run multiple callbacks concurrently in coroutines and return their results
     *
     * @param array $callbacks Array of callbacks to run in coroutines
     * @return array Results of the callbacks
     */
    protected function runMultipleCoroutines(array $callbacks)
    {
        $this->skipIfNoSwoole();

        $results = [];
        $channel = new \Swoole\Coroutine\Channel(count($callbacks));

        \Swoole\Coroutine\run(function () use ($callbacks, $channel) {
            foreach ($callbacks as $index => $callback) {
                \Swoole\Coroutine::create(function () use ($index, $callback, $channel) {
                    try {
                        $result = $callback();
                        $channel->push(['index' => $index, 'result' => $result]);
                    } catch (\Exception $e) {
                        $channel->push(['index' => $index, 'exception' => $e]);
                    }
                });
            }
        });

        for ($i = 0; $i < count($callbacks); $i++) {
            $result = $channel->pop();
            $results[$result['index']] = isset($result['exception'])
                ? ['exception' => $result['exception']]
                : $result['result'];
        }

        return $results;
    }
}