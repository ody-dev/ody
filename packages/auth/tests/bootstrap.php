<?php

/**
 * Bootstrap file for PHPUnit tests
 */

// Load Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Set error reporting to maximum
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Setup Swoole if available
if (extension_loaded('swoole')) {
    // Set the default configuration for Swoole
    \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

    // Note: We'll let PHPUnit handle exceptions naturally
    // Swoole's exit behavior will be managed by the coroutine wait mechanism

    // Set Swoole log level for tests
    \Swoole\Coroutine::set([
        'log_level' => SWOOLE_LOG_INFO,
        'trace_flags' => 0,
        'socket_connect_timeout' => 1,
        'socket_timeout' => 3,
    ]);

    // Register a function to clean up coroutines when PHPUnit is done
    register_shutdown_function(function () {
        // Wait for all coroutines to finish or timeout
        $startTime = microtime(true);
        $timeout = 2; // 2 seconds timeout

        // Check if any coroutines are still running
        while (\Swoole\Coroutine::stats()['coroutine_num'] > 0) {
            // Give up after timeout
            if ((microtime(true) - $startTime) > $timeout) {
                break;
            }

            // Sleep to avoid CPU thrashing
            usleep(10000); // 10ms
        }
    });
}

// Helper functions for tests

/**
 * Run a callback in a new coroutine and wait for the result
 *
 * @param callable $callback Callback to run in a coroutine
 * @return mixed Result of the callback
 */
function runInCoroutine(callable $callback)
{
    if (!extension_loaded('swoole')) {
        throw new \RuntimeException('Swoole extension is required to run coroutines');
    }

    $channel = new \Swoole\Coroutine\Channel(1);

    \Swoole\Coroutine\run(function () use ($callback, $channel) {
        try {
            $result = $callback();
            $channel->push(['result' => $result]);
        } catch (\Throwable $e) {
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
 * Mock the Swoole HTTP Client for testing
 *
 * @param array $responses Array of responses to return
 * @return void
 */
function mockSwooleClient(array $responses = [])
{
    if (!class_exists('\Mockery')) {
        throw new \RuntimeException('Mockery is required to mock the Swoole client');
    }

    $clientMock = \Mockery::mock('overload:Swoole\Coroutine\Http\Client');
    $clientMock->shouldReceive('__construct')->withAnyArgs();
    $clientMock->shouldReceive('setHeaders')->withAnyArgs();

    if (!empty($responses)) {
        foreach ($responses as $endpoint => $responseData) {
            if (strpos($endpoint, 'POST:') === 0) {
                $endpoint = substr($endpoint, 5);
                $clientMock->shouldReceive('post')
                    ->with($endpoint, \Mockery::any())
                    ->andSet('statusCode', $responseData['status'] ?? 200)
                    ->andSet('body', json_encode($responseData['body'] ?? []));
            } elseif (strpos($endpoint, 'GET:') === 0) {
                $endpoint = substr($endpoint, 4);
                $clientMock->shouldReceive('get')
                    ->with($endpoint)
                    ->andSet('statusCode', $responseData['status'] ?? 200)
                    ->andSet('body', json_encode($responseData['body'] ?? []));
            }
        }
    } else {
        // Default mock setup
        $clientMock->shouldReceive('post')->withAnyArgs();
        $clientMock->shouldReceive('get')->withAnyArgs();

        // Set default properties
        $clientMock->statusCode = 200;
        $clientMock->body = json_encode([
            'token' => 'fake_service_token',
            'refreshToken' => 'fake_refresh_token',
            'expiresIn' => 3600,
            'valid' => true,
            'id' => 1,
            'email' => 'test@example.com'
        ]);
    }
}

/**
 * Creates a simple mock JWT token for testing
 *
 * @param array $payload Token payload
 * @param string $key JWT key
 * @return string JWT token
 */
function createTestJwtToken(array $payload, string $key = 'test_secret_key_for_jwt')
{
    if (!class_exists('\Firebase\JWT\JWT')) {
        throw new \RuntimeException('Firebase JWT library is required');
    }

    return \Firebase\JWT\JWT::encode($payload, $key, 'HS256');
}

/**
 * Creates a mock HTTP request for testing
 *
 * @param array $serverParams Server parameters
 * @param array $queryParams Query parameters
 * @param array $postParams POST parameters
 * @param array $cookies Cookies
 * @param array $files Uploaded files
 * @param array $headers HTTP headers
 * @return \Mockery\MockInterface Mocked request
 */
function createMockRequest($serverParams = [], $queryParams = [], $postParams = [], $cookies = [], $files = [], $headers = [])
{
    if (!class_exists('\Mockery')) {
        throw new \RuntimeException('Mockery is required to create mock requests');
    }

    $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');

    // Set up common methods
    $request->shouldReceive('getServerParams')->andReturn($serverParams);
    $request->shouldReceive('getQueryParams')->andReturn($queryParams);
    $request->shouldReceive('getParsedBody')->andReturn($postParams);
    $request->shouldReceive('getCookieParams')->andReturn($cookies);
    $request->shouldReceive('getUploadedFiles')->andReturn($files);

    // Set up headers
    $request->shouldReceive('getHeaders')->andReturn($headers);
    foreach ($headers as $name => $value) {
        $headerValue = is_array($value) ? $value : [$value];
        $request->shouldReceive('getHeader')->with($name)->andReturn($headerValue);
        $request->shouldReceive('getHeaderLine')->with($name)->andReturn(is_array($value) ? implode(',', $value) : $value);
    }

    // Default values for other methods
    $request->shouldReceive('getHeader')->byDefault()->andReturn([]);
    $request->shouldReceive('getHeaderLine')->byDefault()->andReturn('');
    $request->shouldReceive('getAttribute')->byDefault()->andReturn(null);
    $request->shouldReceive('withAttribute')->andReturnUsing(function ($name, $value) use ($request) {
        return $request;
    });

    return $request;
}