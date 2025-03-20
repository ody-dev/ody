<?php

namespace Ody\Auth\Tests\Traits;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Http\Server;

/**
 * Trait for tests that use Swoole functionality
 */
trait SwooleTestingTrait
{
    /**
     * Swoole HTTP server instance
     *
     * @var \Swoole\Http\Server
     */
    protected $swooleServer;

    /**
     * Default server port
     *
     * @var int
     */
    protected $serverPort = 9800;

    /**
     * Route handlers for mock server
     *
     * @var array
     */
    protected $routeHandlers = [];

    /**
     * Run a callback in a Swoole coroutine and return the result
     *
     * @param callable $callback Callback to run
     * @return mixed Result of the callback
     * @throws \Exception If the coroutine throws an exception
     */
    protected function runInCoroutine(callable $callback)
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        $channel = new Channel(1);

        Coroutine::create(function () use ($callback, $channel) {
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
     * Run multiple callbacks concurrently and return their results
     *
     * @param array $callbacks Array of callbacks to run
     * @return array Results of the callbacks
     */
    protected function runConcurrently(array $callbacks)
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        $channel = new Channel(count($callbacks));
        $results = [];

        foreach ($callbacks as $index => $callback) {
            Coroutine::create(function () use ($index, $callback, $channel) {
                try {
                    $result = $callback();
                    $channel->push(['index' => $index, 'result' => $result]);
                } catch (\Exception $e) {
                    $channel->push(['index' => $index, 'exception' => $e]);
                }
            });
        }

        for ($i = 0; $i < count($callbacks); $i++) {
            $result = $channel->pop();

            if (isset($result['exception'])) {
                $results[$result['index']] = ['error' => $result['exception']->getMessage()];
            } else {
                $results[$result['index']] = $result['result'];
            }
        }

        return $results;
    }

    /**
     * Create a Swoole HTTP server for testing
     *
     * @param int $port Port to listen on
     * @param array $handlers Request handlers keyed by path
     * @return \Swoole\Http\Server Server instance
     */
    protected function createMockServer(int $port = null, array $handlers = [])
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        $this->serverPort = $port ?? $this->serverPort;
        $this->routeHandlers = $handlers;

        // Create server
        $this->swooleServer = new Server('127.0.0.1', $this->serverPort, SWOOLE_BASE);

        // Configure server
        $this->swooleServer->set([
            'worker_num' => 1,
            'log_level' => SWOOLE_LOG_ERROR,
            'daemonize' => true,
            'enable_coroutine' => true,
            'task_enable_coroutine' => true,
        ]);

        // Set up HTTP handler
        $this->swooleServer->on('request', function ($request, $response) {
            // Set default headers
            $response->header('Content-Type', 'application/json');

            // Get path and method
            $path = $request->server['request_uri'];
            $method = $request->server['request_method'];

            // Check if we have a handler for this path
            $key = "{$method}:{$path}";

            if (isset($this->routeHandlers[$key])) {
                // Use specific handler
                $handler = $this->routeHandlers[$key];
                $result = $handler($request, $response);

                if ($result !== null && !is_bool($result)) {
                    // If handler returned a value, use it as response
                    $response->end(is_array($result) ? json_encode($result) : $result);
                }
            } elseif (isset($this->routeHandlers[$path])) {
                // Try path-only handler
                $handler = $this->routeHandlers[$path];
                $result = $handler($request, $response);

                if ($result !== null && !is_bool($result)) {
                    $response->end(is_array($result) ? json_encode($result) : $result);
                }
            } else {
                // Default 404 response
                $response->status(404);
                $response->end(json_encode(['error' => 'Not Found']));
            }
        });

        // Start server
        $this->swooleServer->start();

        // Give the server time to start
        sleep(1);

        return $this->swooleServer;
    }

    /**
     * Start a basic mock server with authentication endpoints
     *
     * @param int $port Port to listen on
     * @return \Swoole\Http\Server Server instance
     */
    protected function startMockAuthServer(int $port = null)
    {
        $handlers = [
            '/auth/service' => function ($request, $response) {
                return [
                    'token' => 'service_token_' . uniqid(),
                    'expiresIn' => 3600
                ];
            },
            '/auth/login' => function ($request, $response) {
                $data = json_decode($request->rawContent(), true);
                return [
                    'id' => rand(1, 1000),
                    'email' => $data['username'] ?? 'user@example.com',
                    'token' => 'user_token_' . uniqid(),
                    'refreshToken' => 'refresh_token_' . uniqid(),
                    'expiresIn' => 3600
                ];
            },
            '/auth/validate' => function ($request, $response) {
                return [
                    'valid' => true,
                    'sub' => rand(1, 1000),
                    'email' => 'user@example.com'
                ];
            },
            '/auth/refresh' => function ($request, $response) {
                return [
                    'token' => 'new_token_' . uniqid(),
                    'refreshToken' => 'new_refresh_token_' . uniqid(),
                    'expiresIn' => 3600
                ];
            },
            '/auth/revoke' => function ($request, $response) {
                return [
                    'success' => true
                ];
            }
        ];

        // Add dynamic route handler for user endpoints
        for ($i = 1; $i <= 100; $i++) {
            $handlers["/auth/user/{$i}"] = function ($request, $response) use ($i) {
                return [
                    'id' => $i,
                    'email' => "user{$i}@example.com",
                    'roles' => ['user']
                ];
            };
        }

        return $this->createMockServer($port, $handlers);
    }

    /**
     * Stop the Swoole server
     *
     * @return bool True if server was stopped
     */
    protected function stopMockServer()
    {
        if ($this->swooleServer) {
            $this->swooleServer->shutdown();
            $this->swooleServer = null;
            return true;
        }

        return false;
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        $this->stopMockServer();
        parent::tearDown();
    }
}