<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Events;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

/**
 * Base event subscriber for Doctrine ORM events
 */
class DoctrineEventSubscriber implements EventSubscriber
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::onFlush,
            Events::preFlush,
        ];
    }

    /**
     * Called after an entity is persisted to the database
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);

        // Log the entity creation
        $this->logger->info("Entity created: $entityClass", [
            'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
            'entity_class' => $entityClass,
        ]);
    }

    /**
     * Called after an entity is updated in the database
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     * @return void
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);

        // Log the entity update
        $this->logger->info("Entity updated: $entityClass", [
            'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
            'entity_class' => $entityClass,
        ]);
    }

    /**
     * Called after an entity is removed from the database
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     * @return void
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);

        // Log the entity removal
        $this->logger->info("Entity removed: $entityClass", [
            'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
            'entity_class' => $entityClass,
        ]);
    }

    /**
     * Called when the flush operation is about to execute
     *
     * @param PreFlushEventArgs $args
     * @return void
     */
    public function preFlush(PreFlushEventArgs $args): void
    {
        // You can add pre-flush logic here
    }

    /**
     * Called during the flush operation, before any changes are committed
     *
     * @param OnFlushEventArgs $args
     * @return void
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        // You can add on-flush logic here
    }
}