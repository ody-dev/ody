<?php

namespace Ody\Auth\Tests\Helpers;

/**
 * Helper class for mocking Swoole HTTP client
 * This approach avoids the "class already exists" error
 */
class SwooleClientMock
{
    /**
     * Setup mock for Swoole HTTP client
     *
     * @return void
     */
    public static function setup()
    {
        // Only proceed if we're not in a real Swoole environment
        if (!class_exists('\Swoole\Coroutine\Http\Client') || defined('SWOOLE_MOCK_ACTIVE')) {
            return;
        }

        // Create a global flag to avoid re-defining mock methods
        define('SWOOLE_MOCK_ACTIVE', true);

        // Create a function to patch the client methods
        $patchClientMethods = function ($client) {
            // Only patch if it's a real client
            if (get_class($client) === 'Swoole\Coroutine\Http\Client') {
                // Add dynamic properties for test data
                $client->statusCode = 200;
                $client->body = json_encode([
                    'token' => 'fake_service_token',
                    'refreshToken' => 'fake_refresh_token',
                    'expiresIn' => 3600,
                    'valid' => true,
                    'id' => 1,
                    'email' => 'test@example.com'
                ]);

                // Return true to indicate success for most operations
                return true;
            }

            return null;
        };

        // Add runtime patches for the client methods
        if (method_exists('\Swoole\Coroutine\Http\Client', 'setHeaders')) {
            $originalSetHeaders = ['\Swoole\Coroutine\Http\Client', 'setHeaders'];

            // PHP 8.1+ supports first-class callable objects
            if (PHP_VERSION_ID >= 80100) {
                $originalSetHeadersFn = $originalSetHeaders(...);
            }

            // Replace the setHeaders method
            runkit7_method_redefine('\Swoole\Coroutine\Http\Client', 'setHeaders', function ($headers) use ($patchClientMethods, $originalSetHeaders) {
                $patchClientMethods($this);

                // Call original if possible, otherwise just return true
                if (PHP_VERSION_ID >= 80100) {
                    global $originalSetHeadersFn;
                    return $originalSetHeadersFn->call($this, $headers);
                } else {
                    return true;
                }
            });
        }

        // Other methods to patch
        $methodsToPatch = ['post', 'get'];

        foreach ($methodsToPatch as $method) {
            if (method_exists('\Swoole\Coroutine\Http\Client', $method)) {
                runkit7_method_redefine('\Swoole\Coroutine\Http\Client', $method, function () use ($patchClientMethods) {
                    return $patchClientMethods($this) ?? true;
                });
            }
        }
    }

    /**
     * Alternative way to mock the client using a decorator pattern
     *
     * @return object Mock client
     */
    public static function getMockClient()
    {
        return new class {
            public $statusCode = 200;
            public $body;

            public function __construct() {
                $this->body = json_encode([
                    'token' => 'fake_service_token',
                    'refreshToken' => 'fake_refresh_token',
                    'expiresIn' => 3600,
                    'valid' => true,
                    'id' => 1,
                    'email' => 'test@example.com'
                ]);
            }

            public function setHeaders($headers) {
                return true;
            }

            public function post($path, $data = null) {
                return true;
            }

            public function get($path) {
                return true;
            }
        };
    }
}