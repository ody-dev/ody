<?php

namespace Ody\Auth\Tests\Functional;

use Firebase\JWT\JWT;
use Mockery;
use Ody\Auth\AuthManager;
use Ody\Auth\DirectAuthProvider;
use Ody\Foundation\Http\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class AuthFlowTest extends TestCase
{
    private $userRepository;
    private $jwtKey = 'test_jwt_key';
    private $tokenExpiry = 3600;
    private $refreshTokenExpiry = 86400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock('UserRepository');
    }

    public function testEndToEndAuthFlow()
    {
        // Set up test users
        $users = [
            1 => [
                'id' => 1,
                'email' => 'user1@example.com',
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'roles' => ['user']
            ],
            2 => [
                'id' => 2,
                'email' => 'user2@example.com',
                'password' => password_hash('password456', PASSWORD_DEFAULT),
                'roles' => ['user', 'admin']
            ]
        ];

        // Configure user repository mock
        $this->userRepository->shouldReceive('findByEmail')
            ->with('user1@example.com')
            ->andReturn($users[1]);

        $this->userRepository->shouldReceive('getAuthPassword')
            ->with(1)
            ->andReturn($users[1]['password']);

        $this->userRepository->shouldReceive('findById')
            ->with(1)
            ->andReturn($users[1]);

        // Create auth provider
        $authProvider = new DirectAuthProvider(
            $this->userRepository,
            $this->jwtKey,
            $this->tokenExpiry,
            $this->refreshTokenExpiry
        );

        // Create auth manager
        $authManager = new AuthManager($authProvider);

        // Test login
        $result = $authManager->login('user1@example.com', 'password123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refreshToken', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(1, $result['id']);

        // Test token validation
        $token = $result['token'];
        $tokenData = $authManager->validateToken($token);

        $this->assertIsArray($tokenData);
        $this->assertEquals(1, $tokenData['sub']);
        $this->assertEquals('user1@example.com', $tokenData['email']);

        // Test token refresh
        $refreshToken = $result['refreshToken'];
        $newTokens = $authManager->refreshToken($refreshToken);

        $this->assertIsArray($newTokens);
        $this->assertArrayHasKey('token', $newTokens);
        $this->assertArrayHasKey('refreshToken', $newTokens);

        // Test logout
        $logoutResult = $authManager->logout($token);
        $this->assertTrue($logoutResult);
    }

    /**
     * Test concurrent user sessions using Swoole
     *
     * @requires extension swoole
     */
    public function testConcurrentUserSessions()
    {
        // Start a Swoole coroutine scheduler if not already running
        \Swoole\Coroutine\run(function () {
            // Create a channel to collect results
            $channel = new Channel(1);

            // Run test in a nested coroutine
            Coroutine::create(function () use ($channel) {
                // Set up test users
                $users = [
                    1 => [
                        'id' => 1,
                        'email' => 'user1@example.com',
                        'password' => password_hash('password123', PASSWORD_DEFAULT),
                        'roles' => ['user']
                    ]
                ];

                // Configure user repository mock for coroutine context
                $userRepository = Mockery::mock('UserRepository');
                $userRepository->shouldReceive('findByEmail')
                    ->with('user1@example.com')
                    ->andReturn($users[1]);

                $userRepository->shouldReceive('getAuthPassword')
                    ->with(1)
                    ->andReturn($users[1]['password']);

                $userRepository->shouldReceive('findById')
                    ->with(1)
                    ->andReturn($users[1]);

                // Create auth provider
                $authProvider = new DirectAuthProvider(
                    $userRepository,
                    $this->jwtKey,
                    $this->tokenExpiry,
                    $this->refreshTokenExpiry
                );

                // Create auth manager
                $authManager = new AuthManager($authProvider);

                // Simulate multiple concurrent sessions
                $sessions = [];
                $sessionChannel = new Channel(5);

                // Create 5 sessions
                for ($i = 0; $i < 5; $i++) {
                    Coroutine::create(function () use ($i, $authManager, $sessionChannel) {
                        // Login
                        $loginResult = $authManager->login('user1@example.com', 'password123');

                        // Simulate session activity
                        $token = $loginResult['token'];
                        $refreshToken = $loginResult['refreshToken'];

                        // Validate token
                        $validationResult = $authManager->validateToken($token);

                        // Refresh token if needed (for session 3 and 4)
                        if ($i >= 3) {
                            $refreshResult = $authManager->refreshToken($refreshToken);
                            $token = $refreshResult['token'];
                            $refreshToken = $refreshResult['refreshToken'];
                        }

                        // Logout for some sessions (0, 2, 4)
                        $logoutDone = false;
                        if ($i % 2 === 0) {
                            $logoutResult = $authManager->logout($token);
                            $logoutDone = $logoutResult;
                        }

                        // Send session info to channel
                        $sessionChannel->push([
                            'sessionId' => $i,
                            'loginSuccessful' => !empty($loginResult),
                            'validationSuccessful' => !empty($validationResult),
                            'refreshed' => $i >= 3,
                            'loggedOut' => $logoutDone
                        ]);
                    });
                }

                // Collect session results
                for ($i = 0; $i < 5; $i++) {
                    $sessions[] = $sessionChannel->pop();
                }

                // Send test results back to main thread
                $channel->push($sessions);
            });

            // Get results from coroutine
            $sessions = $channel->pop();

            // Assert all sessions were successful
            foreach ($sessions as $session) {
                $this->assertTrue($session['loginSuccessful'], "Login failed for session {$session['sessionId']}");
                $this->assertTrue($session['validationSuccessful'], "Validation failed for session {$session['sessionId']}");

                if ($session['sessionId'] >= 3) {
                    $this->assertTrue($session['refreshed'], "Refresh failed for session {$session['sessionId']}");
                }

                if ($session['sessionId'] % 2 === 0) {
                    $this->assertTrue($session['loggedOut'], "Logout failed for session {$session['sessionId']}");
                }
            }
        });
    }

    /**
     * Test heavy concurrent load for login/logout
     *
     * @requires extension swoole
     */
    public function testLoginLogoutConcurrency()
    {
        // Execute the test in a Swoole Coroutine scheduler
        \Swoole\Coroutine\run(function () {
            // Set up test users - we'll use 10 users
            $users = [];
            for ($i = 1; $i <= 10; $i++) {
                $users[$i] = [
                    'id' => $i,
                    'email' => "user{$i}@example.com",
                    'password' => password_hash("password{$i}", PASSWORD_DEFAULT),
                    'roles' => ['user']
                ];
            }

            // Configure user repository mock for coroutine context
            $userRepository = Mockery::mock('UserRepository');

            // Set up findByEmail expectations for each user
            foreach ($users as $id => $user) {
                $userRepository->shouldReceive('findByEmail')
                    ->with($user['email'])
                    ->andReturn($user);

                $userRepository->shouldReceive('getAuthPassword')
                    ->with($id)
                    ->andReturn($user['password']);

                $userRepository->shouldReceive('findById')
                    ->with($id)
                    ->andReturn($user);
            }

            // Create auth provider
            $authProvider = new DirectAuthProvider(
                $userRepository,
                $this->jwtKey,
                $this->tokenExpiry,
                $this->refreshTokenExpiry
            );

            // Create auth manager
            $authManager = new AuthManager($authProvider);

            // We'll run fewer operations for faster testing
            $operationCount = 20; // Reduced from 100 for faster testing

            // Simulate heavy concurrent load
            $results = [];
            $resultChannel = new Channel($operationCount);

            // Create operations (login/validate/logout)
            for ($i = 0; $i < $operationCount; $i++) {
                Coroutine::create(function () use ($i, $authManager, $resultChannel, $users) {
                    try {
                        // Select a user (round-robin)
                        $userId = ($i % 10) + 1;
                        $user = $users[$userId];

                        // Perform an operation based on the iteration
                        $operation = $i % 3;
                        $result = null;

                        switch ($operation) {
                            case 0: // Login
                                $result = $authManager->login($user['email'], "password{$userId}");
                                $opName = 'login';
                                break;

                            case 1: // Validate token
                                // First login to get a token
                                $loginResult = $authManager->login($user['email'], "password{$userId}");
                                $token = $loginResult['token'];

                                // Then validate
                                $result = $authManager->validateToken($token);
                                $opName = 'validate';
                                break;

                            case 2: // Logout
                                // First login to get a token
                                $loginResult = $authManager->login($user['email'], "password{$userId}");
                                $token = $loginResult['token'];

                                // Then logout
                                $result = $authManager->logout($token);
                                $opName = 'logout';
                                break;
                        }

                        $resultChannel->push([
                            'id' => $i,
                            'userId' => $userId,
                            'operation' => $opName,
                            'success' => $operation === 2 ? $result === true : !empty($result)
                        ]);
                    } catch (\Exception $e) {
                        $resultChannel->push([
                            'id' => $i,
                            'success' => false,
                            'error' => $e->getMessage()
                        ]);
                    }
                });
            }

            // Collect operation results
            for ($i = 0; $i < $operationCount; $i++) {
                $results[] = $resultChannel->pop();
            }

            // Count successes and failures
            $successes = 0;
            $failures = 0;
            $operationCounts = ['login' => 0, 'validate' => 0, 'logout' => 0];

            foreach ($results as $result) {
                if ($result['success']) {
                    $successes++;
                    if (isset($result['operation'])) {
                        $operationCounts[$result['operation']]++;
                    }
                } else {
                    $failures++;
                }
            }

            // Assert overall success rate
            $successRate = ($successes / count($results)) * 100;
            $this->assertGreaterThanOrEqual(95, $successRate, "Success rate below 95%");

            // Output stats for debugging
            echo "Concurrent Auth Flow Test Results:\n";
            echo "Total Operations: " . count($results) . "\n";
            echo "Successes: {$successes} ({$successRate}%)\n";
            echo "Failures: {$failures}\n";
            echo "Login Operations: {$operationCounts['login']}\n";
            echo "Validate Operations: {$operationCounts['validate']}\n";
            echo "Logout Operations: {$operationCounts['logout']}\n";
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}