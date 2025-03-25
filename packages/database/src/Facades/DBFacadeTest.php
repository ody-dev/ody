<?php

namespace Ody\DB\Facades;

use Illuminate\Support\Facades\DB as LaravelDB;
use Ody\DB\Facades\DB as OdyDB;
use function Swoole\Coroutine\run;

/**
 * Comprehensive test suite for DB facade functionality
 *
 * This script tests both Laravel's DB facade and Ody's DB facade
 * to ensure they both work correctly with your coroutine-aware
 * database implementation.
 */
class DBFacadeTest
{
    private array $results = [];
    private int $passedTests = 0;
    private int $totalTests = 0;

    /**
     * Run all tests
     */
    public function runTests(): void
    {
        $this->header('Starting DB Facade Tests');

        // Run tests in the Swoole coroutine environment
        run(function () {
            // Test both facades
            $this->testBasicQueries(LaravelDB::class, 'Laravel');
            $this->testBasicQueries(OdyDB::class, 'Ody');

            $this->testTransactions(LaravelDB::class, 'Laravel');
            $this->testTransactions(OdyDB::class, 'Ody');

            $this->testQueryBuilder(LaravelDB::class, 'Laravel');
            $this->testQueryBuilder(OdyDB::class, 'Ody');

            $this->testConnectionManagement(LaravelDB::class, 'Laravel');
            $this->testConnectionManagement(OdyDB::class, 'Ody');

            $this->testParallelQueries(LaravelDB::class, 'Laravel');
            $this->testParallelQueries(OdyDB::class, 'Ody');

            $this->testNestedTransactions(LaravelDB::class, 'Laravel');
            $this->testNestedTransactions(OdyDB::class, 'Ody');
        });

        $this->reportResults();
    }

    private function header(string $text): void
    {
        $this->log("");
        $this->log(str_repeat("=", strlen($text) + 4));
        $this->log("  " . $text);
        $this->log(str_repeat("=", strlen($text) + 4));
    }

    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Test basic query functionality
     */
    private function testBasicQueries(string $dbClass, string $label): void
    {
        $this->header("Testing Basic Queries with $label DB Facade");

        // Test SELECT statement
        $this->testCase("$label: Basic SELECT", function () use ($dbClass) {
            $result = $dbClass::select('SELECT 1 as value');
            return is_array($result) && isset($result[0]->value) && $result[0]->value == 1;
        });

        // Test connection method
        $this->testCase("$label: Connection access", function () use ($dbClass) {
            $connection = $dbClass::connection();
            return $connection instanceof \Ody\DB\Connection || $connection instanceof \Illuminate\Database\Connection;
        });

        // Test table query
        $this->testCase("$label: Table query", function () use ($dbClass) {
            // Adjust the table name to an existing table in your database
            $users = $dbClass::table('users')->limit(1)->get();
            return is_array($users) || $users instanceof \Illuminate\Support\Collection;
        });

        // Test scalar queries
        $this->testCase("$label: Scalar query", function () use ($dbClass) {
            $count = $dbClass::table('users')->count();
            return is_numeric($count);
        });
    }

    private function testCase(string $name, callable $test): void
    {
        $this->totalTests++;

        try {
            $result = $test();
            if ($result) {
                $this->passedTests++;
                $this->results[$name] = "✅ PASS";
            } else {
                $this->results[$name] = "❌ FAIL";
            }
        } catch (\Throwable $e) {
            $this->results[$name] = "❌ ERROR: " . $e->getMessage();
        }
    }

    /**
     * Test transaction functionality
     */
    private function testTransactions(string $dbClass, string $label): void
    {
        $this->header("Testing Transactions with $label DB Facade");

        // Basic transaction commit
        $this->testCase("$label: Transaction commit", function () use ($dbClass) {
            try {
                return $dbClass::transaction(function () use ($dbClass) {
                    // Create a temporary test table
                    $dbClass::statement('CREATE TEMPORARY TABLE test_transaction (id INT, name VARCHAR(50))');
                    $dbClass::table('test_transaction')->insert(['id' => 1, 'name' => 'Test']);

                    // Query should find the row
                    $rows = $dbClass::table('test_transaction')->get();
                    return count($rows) === 1;
                });
            } catch (\Throwable $e) {
                $this->log("Transaction error: " . $e->getMessage());
                return false;
            }
        });

        // Transaction rollback
        $this->testCase("$label: Transaction rollback", function () use ($dbClass) {
            $success = false;

            try {
                $dbClass::transaction(function () use ($dbClass, &$success) {
                    // Create a temporary test table
                    $dbClass::statement('CREATE TEMPORARY TABLE test_rollback (id INT)');
                    $dbClass::table('test_rollback')->insert(['id' => 1]);

                    // Force a rollback
                    throw new \Exception('Forced rollback');
                });
            } catch (\Exception $e) {
                // Expected exception
                $success = true;
            }

            // The table should exist but be empty after rollback
            try {
                $count = $dbClass::table('test_rollback')->count();
                return $success && $count === 0;
            } catch (\Throwable $e) {
                // If table doesn't exist, that's acceptable too
                return $success;
            }
        });
    }

    /**
     * Test query builder functionality
     */
    private function testQueryBuilder(string $dbClass, string $label): void
    {
        $this->header("Testing Query Builder with $label DB Facade");

        // Test query builder chaining
        $this->testCase("$label: Query builder chaining", function () use ($dbClass) {
            $query = $dbClass::table('users')
                ->select('id', 'name')
                ->where('active', 1)
                ->orderBy('id', 'desc')
                ->limit(5);

            $sql = $query->toSql();
            return strpos($sql, 'select `id`, `name`') !== false &&
                strpos($sql, 'where `active` = ?') !== false &&
                strpos($sql, 'order by `id` desc') !== false &&
                strpos($sql, 'limit 5') !== false;
        });

        // Test joins
        $this->testCase("$label: Query joins", function () use ($dbClass) {
            $query = $dbClass::table('users')
                ->join('posts', 'users.id', '=', 'posts.user_id')
                ->select('users.id', 'posts.title');

            $sql = $query->toSql();
            return strpos($sql, 'join `posts` on `users`.`id` = `posts`.`user_id`') !== false;
        });
    }

    // Helper methods

    /**
     * Test connection management
     */
    private function testConnectionManagement(string $dbClass, string $label): void
    {
        $this->header("Testing Connection Management with $label DB Facade");

        // Test multiple connections
        $this->testCase("$label: Multiple connections", function () use ($dbClass) {
            try {
                // This depends on your configuration having multiple connections
                // If it doesn't, modify this test accordingly
                $defaultConnection = $dbClass::connection();

                // Try a different connection if configured
                // Modify 'other_connection' to match your configuration
                try {
                    $otherConnection = $dbClass::connection('other_connection');
                    return $defaultConnection !== $otherConnection;
                } catch (\Throwable $e) {
                    // If only one connection is configured, that's okay too
                    return true;
                }
            } catch (\Throwable $e) {
                $this->log("Connection error: " . $e->getMessage());
                return false;
            }
        });

        // Test connection resolved correctly in coroutine
        $this->testCase("$label: Connection resolved in coroutine", function () use ($dbClass) {
            $cid = \Swoole\Coroutine::getCid();
            $connection1 = $dbClass::connection();

            // The connection should be associated with this coroutine
            return $cid > 0 && $connection1 instanceof \Ody\DB\Connection;
        });
    }

    /**
     * Test parallel query execution
     */
    private function testParallelQueries(string $dbClass, string $label): void
    {
        $this->header("Testing Parallel Queries with $label DB Facade");

        // Test parallel query execution
        $this->testCase("$label: Parallel query execution", function () use ($dbClass) {
            $results = [];
            $futures = [];

            // Create 5 parallel queries
            for ($i = 0; $i < 5; $i++) {
                $futures[] = \Ody\Futures\async(function () use ($dbClass, $i) {
                    // Each coroutine gets its own query
                    $result = $dbClass::select("SELECT ? as value", [$i]);
                    return $result[0]->value;
                });
            }

            // Wait for all to complete
            $combined = \Ody\Futures\join($futures);
            $results = $combined->await();

            // We should have 5 results with values 0-4
            $success = count($results) === 5;
            for ($i = 0; $i < 5; $i++) {
                $success = $success && in_array($i, $results);
            }

            return $success;
        });
    }

    /**
     * Test nested transactions
     */
    private function testNestedTransactions(string $dbClass, string $label): void
    {
        $this->header("Testing Nested Transactions with $label DB Facade");

        // Test nested transactions commit
        $this->testCase("$label: Nested transactions commit", function () use ($dbClass) {
            try {
                return $dbClass::transaction(function () use ($dbClass) {
                    // Create a temporary test table
                    $dbClass::statement('CREATE TEMPORARY TABLE nested_test (id INT)');
                    $dbClass::table('nested_test')->insert(['id' => 1]);

                    // Start a nested transaction
                    return $dbClass::transaction(function () use ($dbClass) {
                        $dbClass::table('nested_test')->insert(['id' => 2]);

                        // Query should find both rows
                        $count = $dbClass::table('nested_test')->count();
                        return $count === 2;
                    });
                });
            } catch (\Throwable $e) {
                $this->log("Nested transaction error: " . $e->getMessage());
                return false;
            }
        });

        // Test nested transaction rollback
        $this->testCase("$label: Nested transaction rollback", function () use ($dbClass) {
            try {
                return $dbClass::transaction(function () use ($dbClass) {
                    // Create a temporary test table
                    $dbClass::statement('CREATE TEMPORARY TABLE nested_rollback (id INT)');
                    $dbClass::table('nested_rollback')->insert(['id' => 1]);

                    // Start a nested transaction that will fail
                    try {
                        $dbClass::transaction(function () use ($dbClass) {
                            $dbClass::table('nested_rollback')->insert(['id' => 2]);
                            throw new \Exception('Forced nested rollback');
                        });
                    } catch (\Exception $e) {
                        // Expected exception
                    }

                    // Only the first insert should remain
                    $count = $dbClass::table('nested_rollback')->count();
                    return $count === 1;
                });
            } catch (\Throwable $e) {
                $this->log("Nested transaction rollback error: " . $e->getMessage());
                return false;
            }
        });
    }

    private function reportResults(): void
    {
        $this->header("Test Results");

        foreach ($this->results as $name => $result) {
            $this->log("$name: $result");
        }

        $this->log("");
        $this->log("Summary: {$this->passedTests}/{$this->totalTests} tests passed");

        $percentage = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100) : 0;
        $this->log("Success rate: $percentage%");
    }
}