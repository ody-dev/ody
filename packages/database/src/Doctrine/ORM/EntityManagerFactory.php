<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Swoole\Coroutine;
use Ody\DB\Doctrine\PooledConnection;
use Ody\DB\Doctrine\ConnectionResolver;

/**
 * Factory class for creating EntityManagers with connection pooling
 */
class EntityManagerFactory
{
    /**
     * Store EntityManagers per coroutine
     *
     * @var array
     */
    protected static array $entityManagers = [];

    /**
     * Create a new EntityManager with a pooled connection
     *
     * @param array $connectionParams
     * @param array $ormConfig
     * @param string $connectionName
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    public static function create(
        array $connectionParams,
        array $ormConfig = [],
        string $connectionName = 'default'
    ): EntityManager {
        $cid = Coroutine::getCid();

        // If we're in a coroutine and using pooling, we want to reuse EntityManagers per coroutine
        $usePooling = ($connectionParams['use_pooling'] ?? false) && extension_loaded('swoole') && $cid >= 0;

        if ($usePooling && isset(self::$entityManagers[$connectionName][$cid])) {
            return self::$entityManagers[$connectionName][$cid];
        }

        // Create the ORM configuration
        $config = self::createConfiguration($ormConfig);

        // Create the DBAL connection
        $connection = self::createConnection($connectionParams, $connectionName);

        // Create the EntityManager
        $entityManager = new EntityManager($connection, $config);

        // If using pooling, store for this coroutine and set up cleanup
        if ($usePooling) {
            if (!isset(self::$entityManagers[$connectionName])) {
                self::$entityManagers[$connectionName] = [];
            }

            self::$entityManagers[$connectionName][$cid] = $entityManager;

            // Clean up when the coroutine ends
            Coroutine::defer(function() use ($connectionName, $cid) {
                if (isset(self::$entityManagers[$connectionName][$cid])) {
                    // Get the connection
                    $conn = self::$entityManagers[$connectionName][$cid]->getConnection();

                    // If it's our pooled connection, close it properly
                    if ($conn instanceof PooledConnection) {
                        $conn->close();
                    }

                    // Remove the reference
                    unset(self::$entityManagers[$connectionName][$cid]);
                }
            });
        }

        return $entityManager;
    }

    /**
     * Create a Doctrine ORM configuration object
     *
     * @param array $config
     * @return \Doctrine\ORM\Configuration
     */
    protected static function createConfiguration(array $config): \Doctrine\ORM\Configuration
    {
        $isDevMode = $config['dev_mode'] ?? false;
        $proxyDir = $config['proxy_dir'] ?? null;
        $cache = $config['cache'] ?? null;

        // Set up configuration using ORM's Setup helper
        if (isset($config['attribute_driver']) && $config['attribute_driver']) {
            // Using attribute metadata
            $paths = $config['entity_paths'] ?? [];
            $configuration = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode, $proxyDir, $cache);
        } elseif (isset($config['annotation_driver']) && $config['annotation_driver']) {
            // Using annotation metadata
            $paths = $config['entity_paths'] ?? [];
            $configuration = ORMSetup::createAnnotationMetadataConfiguration($paths, $isDevMode, $proxyDir, $cache);
        } else {
            // Using XML or YAML metadata
            $paths = $config['metadata_dirs'] ?? [];
            $configuration = ORMSetup::createXMLMetadataConfiguration($paths, $isDevMode, $proxyDir, $cache);
        }

        // Set additional configuration options
        if (isset($config['auto_generate_proxy_classes'])) {
            $configuration->setAutoGenerateProxyClasses($config['auto_generate_proxy_classes']);
        }

        if (isset($config['entity_namespace'])) {
            $configuration->addEntityNamespace('default', $config['entity_namespace']);
        }

        return $configuration;
    }

    /**
     * Create a DBAL connection
     *
     * @param array $params
     * @param string $name
     * @return \Doctrine\DBAL\Connection
     */
    protected static function createConnection(array $params, string $name): \Doctrine\DBAL\Connection
    {
        // Ensure the 'wrapperClass' is our pooled connection class if using pooling
        if (($params['use_pooling'] ?? false) && extension_loaded('swoole')) {
            $params['wrapperClass'] = PooledConnection::class;
            $params['connection_name'] = $name;
        }

        // Get connection through DriverManager (which is patched to use our pool)
        return DriverManager::getConnection($params);
    }

    /**
     * Close all entity managers and their connections
     */
    public static function closeAll(): void
    {
        logger()->info("Closing all pooled EntityManagers");

        foreach (self::$entityManagers as $connectionName => $managers) {
            foreach ($managers as $cid => $manager) {
                $conn = $manager->getConnection();

                // If it's our pooled connection, close it properly
                if ($conn instanceof PooledConnection) {
                    $conn->close();
                }

                unset(self::$entityManagers[$connectionName][$cid]);
            }
        }

        self::$entityManagers = [];
    }
}