<?php

namespace Ody\CQRS\Discovery;

use Ody\CQRS\Middleware\MiddlewareRegistry;
use Psr\Log\LoggerInterface;

class MiddlewareScanner
{
    /**
     * @param MiddlewareRegistry $middlewareRegistry
     * @param FileScanner $fileScanner
     * @param LoggerInterface $logger
     */
    public function __construct(
        private MiddlewareRegistry $middlewareRegistry,
        private FileScanner        $fileScanner,
        private LoggerInterface    $logger
    )
    {
    }

    /**
     * Scan and register middleware from specified paths
     *
     * @param array $paths
     * @return void
     */
    public function scanAndRegister(array $paths): void
    {
        if (empty($paths)) {
            return;
        }

        try {
            $this->middlewareRegistry->registerMiddleware($paths);
        } catch (\Throwable $e) {
            $this->logger->error("Error registering middleware: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}