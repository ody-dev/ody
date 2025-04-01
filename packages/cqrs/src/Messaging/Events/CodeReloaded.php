<?php

namespace Ody\Framework\Events;

/**
 * Event emitted when code has been reloaded
 */
class CodeReloaded
{
    /**
     * @var array|null List of files that were changed, if available
     */
    private ?array $changedFiles;

    /**
     * @param array|null $changedFiles List of files that were changed
     */
    public function __construct(?array $changedFiles = null)
    {
        $this->changedFiles = $changedFiles;
    }

    /**
     * Get the list of changed files
     *
     * @return array|null
     */
    public function getChangedFiles(): ?array
    {
        return $this->changedFiles;
    }
}