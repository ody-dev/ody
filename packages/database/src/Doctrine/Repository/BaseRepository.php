<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\QueryBuilder;

/**
 * Base repository for all entity repositories
 *
 * @template T of object
 */
abstract class BaseRepository
{
    /**
     * @var string
     */
    protected string $entityClass;

    public function __construct(
        protected EntityManagerInterface $entityManager
    )
    {
    }

    /**
     * Create a new query builder
     *
     * @param string $alias
     * @param string|null $indexBy
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        return $this->getRepository()->createQueryBuilder($alias, $indexBy);
    }

    /**
     * Get the repository for this entity
     *
     * @return EntityRepository<object>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->entityClass);
    }

    /**
     * Get entity manager
     *
     * @return EntityManagerInterface
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Find an entity by its primary key
     *
     * @param int $id
     * @return object|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function find(int $id): ?object
    {
        return $this->entityManager->find($this->entityClass, $id);
    }

    /**
     * Find all entities
     *
     * @return array<T>
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * Find entities by criteria
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find a single entity by criteria
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @return object|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return $this->getRepository()->findOneBy($criteria, $orderBy);
    }

    /**
     * Persist an entity
     *
     * @param object $entity
     * @param bool $flush
     * @return void
     */
    public function save(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an entity
     *
     * @param object $entity
     * @param bool $flush
     * @return void
     */
    public function remove(object $entity, bool $flush = true): void
    {
        $this->entityManager->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}