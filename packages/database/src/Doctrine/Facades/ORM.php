<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Facades;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Swoole\Coroutine;

/**
 * Facade for Doctrine ORM
 *
 * @method static \Doctrine\ORM\QueryBuilder createQueryBuilder()
 * @method static object|null find(string $entityName, mixed $id)
 * @method static object getReference(string $entityName, mixed $id)
 * @method static void persist(object $entity)
 * @method static void remove(object $entity)
 * @method static void flush()
 * @method static void clear()
 * @method static void refresh(object $entity)
 * @method static void detach(object $entity)
 * @method static bool contains(object $entity)
 */
class ORM
{
    /**
     * Store entity managers by coroutine ID
     *
     * @var array<int, EntityManagerInterface>
     */
    protected static array $entityManagers = [];

    /**
     * Entity manager resolver
     *
     * @var callable|null
     */
    protected static $resolver;

    /**
     * Pass static method calls to the entity manager
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return static::entityManager()->$method(...$args);
    }

    /**
     * Get an entity manager instance
     *
     * @param string|null $name
     * @return EntityManagerInterface
     */
    public static function entityManager(?string $name = null): EntityManagerInterface
    {
        $cid = Coroutine::getCid();

        // For non-coroutine context, use the resolver directly
        if ($cid < 0) {
            return call_user_func(static::$resolver, $name);
        }

        $connectionKey = $cid . '-' . ($name ?? 'default');

        if (!isset(self::$entityManagers[$connectionKey])) {
            self::$entityManagers[$connectionKey] = call_user_func(static::$resolver, $name);

            // Register a defer callback to clean up the entity manager when the coroutine ends
            Coroutine::defer(function () use ($connectionKey) {
                if (isset(self::$entityManagers[$connectionKey])) {
                    try {
                        // Check if there's an active transaction and roll it back
                        if (self::$entityManagers[$connectionKey]->getConnection()->isTransactionActive()) {
                            self::$entityManagers[$connectionKey]->getConnection()->rollBack();
                        }

                        // Close the entity manager
                        self::$entityManagers[$connectionKey]->close();
                    } catch (\Throwable $e) {
                        // Ignore exceptions during cleanup
                        logger()->error("Error during ORM entity manager cleanup: " . $e->getMessage());
                    }

                    // Remove the entity manager reference
                    unset(self::$entityManagers[$connectionKey]);
                }
            });
        }

        return self::$entityManagers[$connectionKey];
    }

    /**
     * Set the entity manager resolver
     *
     * @param callable $resolver
     * @return void
     */
    public static function setResolver(callable $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Get repository for entity
     *
     * @param string $entityName
     * @return ObjectRepository
     */
    public static function getRepository(string $entityName): ObjectRepository
    {
        return static::entityManager()->getRepository($entityName);
    }

    /**
     * Execute a callback in a transaction
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        $em = static::entityManager();

        // Start transaction
        $em->beginTransaction();

        try {
            $result = $callback($em);
            $em->flush();
            $em->commit();

            return $result;
        } catch (\Throwable $e) {
            $em->rollback();
            throw $e;
        }
    }
}