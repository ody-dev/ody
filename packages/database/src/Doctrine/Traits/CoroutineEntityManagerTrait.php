<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Ody\DB\Doctrine\Facades\ORM;
use Swoole\Coroutine;
use Throwable;

/**
 * Trait to provide convenient methods for working with entity managers in coroutines
 */
trait CoroutineEntityManagerTrait
{
    /**
     * Execute a callback in a transaction within a new coroutine
     *
     * @param callable $callback
     * @param string|null $connectionName
     * @return mixed
     */
    protected function transactionalCoroutine(callable $callback, ?string $connectionName = null): mixed
    {
        return $this->withCoroutineEntityManager(function (EntityManagerInterface $em) use ($callback) {
            return $em->transactional(function (EntityManagerInterface $em) use ($callback) {
                return $callback($em);
            });
        }, $connectionName);
    }

    /**
     * Execute a callback in a new coroutine with a dedicated entity manager
     *
     * @param callable $callback
     * @param string|null $connectionName
     * @return mixed
     */
    protected function withCoroutineEntityManager(callable $callback, ?string $connectionName = null): mixed
    {
        return Coroutine::create(function () use ($callback, $connectionName) {
            $em = ORM::entityManager($connectionName);

            try {
                return $callback($em);
            } catch (Throwable $e) {
                // Handle any exceptions that occur in the coroutine
                logger()->error("Error in coroutine with entity manager: " . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Execute a batch of operations in parallel coroutines, with one entity manager per coroutine
     *
     * @param array $items
     * @param callable $callback Function that receives ($item, $em) as parameters
     * @param string|null $connectionName
     * @return array Results from each coroutine
     */
    protected function parallelEntityManagerOperations(array $items, callable $callback, ?string $connectionName = null): array
    {
        $results = [];
        $wg = new \Swoole\Coroutine\WaitGroup();

        foreach ($items as $key => $item) {
            $wg->add();

            Coroutine::create(function () use ($item, $callback, $connectionName, $key, &$results, $wg) {
                $em = ORM::entityManager($connectionName);

                try {
                    $results[$key] = $callback($item, $em);
                } catch (Throwable $e) {
                    logger()->error("Error in parallel entity manager operation: " . $e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                        'key' => $key
                    ]);

                    // Store the exception in the results
                    $results[$key] = $e;
                } finally {
                    $wg->done();
                }
            });
        }

        // Wait for all operations to complete
        $wg->wait();

        return $results;
    }
}