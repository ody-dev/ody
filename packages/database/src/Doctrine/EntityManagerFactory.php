<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Setup;
use Ody\DB\Doctrine\Facades\DBAL;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class EntityManagerFactory
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Create a new EntityManager instance
     *
     * @param string|null $connectionName
     * @return EntityManagerInterface
     * @throws \Doctrine\ORM\ORMException
     */
    public function create(?string $connectionName = null): EntityManagerInterface
    {
        // Get connection from DBAL (which is already integrated with the connection pool)
        $connection = DBAL::connection($connectionName);

        // Create configuration
        $config = $this->createConfiguration();

        // Create entity manager with the connection and configuration
        $entityManager = new EntityManager($connection, $config);

        // Register event subscribers if enabled
        if (config('doctrine.enable_events', true)) {
            $this->registerEventSubscribers($entityManager);
        }

        return $entityManager;
    }

    /**
     * Create Doctrine ORM configuration
     *
     * @return Configuration
     */
    protected function createConfiguration(): Configuration
    {
        $appEnv = config('app.environment', 'production');
        $isDevMode = in_array($appEnv, ['local', 'development', 'testing']);

        // Get entity paths from config
        $entityPaths = config('doctrine.entity_paths', [
            base_path('app/Entities')
        ]);

        // Create configuration
        $config = ORMSetup::createAttributeMetadataConfiguration(
            $entityPaths,
            $isDevMode,
            config('doctrine.proxy_dir', storage_path('proxies')),
            $this->createCache()
        );

        // Set naming strategy
        if ($namingStrategy = config('doctrine.naming_strategy')) {
            $strategyClass = $namingStrategy;
            if ($strategyClass === \Doctrine\ORM\Mapping\UnderscoreNamingStrategy::class) {
                // Set the UnderscoreNamingStrategy with CASE_LOWER for snake_case column names
                $config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy(CASE_LOWER));
            } else {
                $config->setNamingStrategy(new $strategyClass());
            }
        }

        // Set custom types
        foreach (config('doctrine.types', []) as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }

        // Configure proxy settings
        $config->setAutoGenerateProxyClasses($isDevMode);

        return $config;
    }

    /**
     * Create cache adapter based on environment
     *
     * @return \Psr\Cache\CacheItemPoolInterface
     */
    protected function createCache(): \Psr\Cache\CacheItemPoolInterface
    {
        $cacheType = config('doctrine.cache.type', 'array');

        switch ($cacheType) {
            case 'file':
                return new FilesystemAdapter(
                    'doctrine',
                    config('doctrine.cache.ttl', 3600),
                    config('doctrine.cache.directory', storage_path('cache/doctrine'))
                );
            case 'redis':
                // Redis implementation can be added here if needed
                // For now, fall back to array cache in this case
            case 'array':
            default:
                return new ArrayAdapter();
        }
    }

    /**
     * Register event subscribers with the entity manager
     *
     * @param EntityManagerInterface $entityManager
     * @return void
     */
    protected function registerEventSubscribers(EntityManagerInterface $entityManager): void
    {
        $eventManager = $entityManager->getEventManager();

        // Register the default event subscriber
        $eventManager->addEventSubscriber(
            new Events\DoctrineEventSubscriber($this->container->get('logger'))
        );

        // Register additional custom event subscribers
        $customSubscribers = config('doctrine.event_subscribers', []);

        foreach ($customSubscribers as $subscriberClass) {
            if (class_exists($subscriberClass)) {
                $subscriber = $this->container->has($subscriberClass)
                    ? $this->container->get($subscriberClass)
                    : new $subscriberClass();

                $eventManager->addEventSubscriber($subscriber);
            }
        }
    }
}