<?php
//
//namespace App\Middleware;
//
//use Ody\CQRS\Middleware\Around;
//use Ody\CQRS\Middleware\MethodInvocation;
//
///**
// * Example middleware for caching query results
// */
//class CachingMiddleware
//{
//    /**
//     * @var \Psr\SimpleCache\CacheInterface
//     */
//    private $cache;
//
//    /**
//     * Constructor
//     *
//     * @param \Psr\SimpleCache\CacheInterface $cache
//     */
//    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
//    {
//        $this->cache = $cache;
//    }
//
//    /**
//     * Cache query results
//     *
//     * @param MethodInvocation $invocation The method invocation
//     * @return mixed The cached result or the result of the invocation
//     */
//    #[Around(pointcut: "Ody\\CQRS\\Bus\\QueryBus::executeHandler")]
//    public function cacheQueryResults(MethodInvocation $invocation): mixed
//    {
//        $args = $invocation->getArguments();
//        $query = $args[0] ?? null;
//
//        if (!$query) {
//            return $invocation->proceed();
//        }
//
//        // Only cache certain query types
//        if (!property_exists($query, 'cacheable') || !$query->cacheable) {
//            return $invocation->proceed();
//        }
//
//        // Generate a cache key based on the query class and properties
//        $cacheKey = $this->generateCacheKey($query);
//
//        // Check if we have a cached result
//        if ($this->cache->has($cacheKey)) {
//            return $this->cache->get($cacheKey);
//        }
//
//        // Execute the query
//        $result = $invocation->proceed();
//
//        // Cache the result (default TTL: 1 hour)
//        $ttl = property_exists($query, 'cacheTtl') ? $query->cacheTtl : 3600;
//        $this->cache->set($cacheKey, $result, $ttl);
//
//        return $result;
//    }
//
//    /**
//     * Generate a cache key for a query
//     *
//     * @param object $query The query
//     * @return string The cache key
//     */
//    private function generateCacheKey(object $query): string
//    {
//        $queryClass = get_class($query);
//        $queryData = get_object_vars($query);
//
//        // Remove non-cacheable properties
//        unset($queryData['cacheable']);
//        unset($queryData['cacheTtl']);
//
//        // Sort to ensure consistent keys
//        ksort($queryData);
//
//        return 'query:' . md5($queryClass . serialize($queryData));
//    }
//}