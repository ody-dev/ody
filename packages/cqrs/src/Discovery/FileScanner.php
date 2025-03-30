<?php

namespace Ody\CQRS\Discovery;

use Psr\Log\LoggerInterface;

class FileScanner
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    /**
     * Scan a directory recursively for PHP files
     *
     * @param string $directory
     * @return array List of PHP files found
     */
    public function scanDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            $this->logger->warning("Directory does not exist: {$directory}");
            return [];
        }

        $files = [];
        $this->scanDirectoryRecursive($directory, $files);

        return $files;
    }

    /**
     * Scan a directory recursively and collect PHP files
     *
     * @param string $directory
     * @param array $files Reference to the files array
     * @return void
     */
    private function scanDirectoryRecursive(string $directory, array &$files): void
    {
        try {
            // Get all PHP files in the current directory
            $phpFiles = glob($directory . '/*.php');
            if (is_array($phpFiles)) {
                $files = array_merge($files, $phpFiles);
            }

            // Get all subdirectories
            $subdirectories = glob($directory . '/*', GLOB_ONLYDIR);
            if (!is_array($subdirectories)) {
                return;
            }

            // Scan each subdirectory
            foreach ($subdirectories as $subdirectory) {
                $this->scanDirectoryRecursive($subdirectory, $files);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error scanning directory {$directory}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}