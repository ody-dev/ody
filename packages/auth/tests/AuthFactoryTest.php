<?php

namespace Ody\Auth\Tests;

use Firebase\JWT\JWT;
use Mockery;
use Ody\Auth\AuthFactory;
use Ody\Auth\AuthManager;
use Ody\Auth\AuthProviderInterface;
use Ody\Auth\DirectAuthProvider;
use Ody\Auth\RemoteAuthProvider;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class AuthFactoryTest extends TestCase
{
    public function testCreateDirectProvider()
    {
        $userRepo = Mockery::mock('UserRepository');
        $jwtKey = 'test_key';
        $tokenExpiry = 3600;
        $refreshTokenExpiry = 86400;

        $provider = AuthFactory::createDirectProvider(
            $userRepo,
            $jwtKey,
            $tokenExpiry,
            $refreshTokenExpiry
        );

        $this->assertInstanceOf(DirectAuthProvider::class, $provider);
    }

    public function testCreateRemoteProvider()
    {
        // Skip this test if Swoole coroutine extension is not available
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for this test.');
            return;
        }

        // Run the test in a coroutine context
        $this->runInCoroutine(function() {
            $authServiceHost = 'localhost';
            $authServicePort = 9501;
            $serviceId = 'test_service';
            $serviceSecret = 'test_secret';

            // Mock the HTTP client response
            $this->mockHttpClient();

            $provider = AuthFactory::createRemoteProvider(
                $authServiceHost,
                $authServicePort,
                $serviceId,
                $serviceSecret
            );

            $this->assertInstanceOf(RemoteAuthProvider::class, $provider);
        });
    }

    public function testCreateFromConfig()
    {
        $userRepo = Mockery::mock('UserRepository');

        // Test direct provider
        $config = [
            'provider' => 'direct',
            'userRepository' => $userRepo,
            'jwtKey' => 'test_key',
            'tokenExpiry' => 3600,
            'refreshTokenExpiry' => 86400
        ];

        $provider = AuthFactory::createFromConfig($config);
        $this->assertInstanceOf(DirectAuthProvider::class, $provider);

        // For remote provider, only test if Swoole is available
        if (extension_loaded('swoole')) {
            // Run in a coroutine context
            $this->runInCoroutine(function() use (&$provider) {
                // Mock the HTTP client response
                $this->mockHttpClient();

                $config = [
                    'provider' => 'remote',
                    'authServiceHost' => 'localhost',
                    'authServicePort' => 9501,
                    'serviceId' => 'test_service',
                    'serviceSecret' => 'test_secret'
                ];

                $provider = AuthFactory::createFromConfig($config);
            });

            $this->assertInstanceOf(RemoteAuthProvider::class, $provider);
        }

        // Test invalid provider
        $config = [
            'provider' => 'invalid'
        ];

        $this->expectException(\InvalidArgumentException::class);
        AuthFactory::createFromConfig($config);
    }

    /**
     * Run a callback in a Swoole coroutine
     *
     * @param callable $callback The callback to run
     * @return mixed Result of the callback
     */
    protected function runInCoroutine(callable $callback)
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is required for coroutine tests.');
            return null;
        }

        $result = null;
        $exception = null;

        \Swoole\Coroutine\run(function() use ($callback, &$result, &$exception) {
            try {
                $result = $callback();
            } catch (\Exception $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Set up a mock for the HTTP client
     */
    protected function mockHttpClient()
    {
        // This just demonstrates the concept - actual implementation would depend on your testing approach

        // One approach is to monkey patch the \Swoole\Coroutine\Http\Client class methods
        // Another is to set up a local HTTP server for testing
        // For simplicity, we'll leave this as a placeholder
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}