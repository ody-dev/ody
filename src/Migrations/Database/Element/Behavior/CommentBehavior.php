<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\Element\Behavior;

use Ody\DB\Migrations\Database\Element\MigrationTable;

trait CommentBehavior
{
    private ?string $comment = null;

    public function setComment(?string $comment): MigrationTable
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function unsetComment(): MigrationTable
    {
        return $this->setComment('');
    }
}
