<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\Element\Behavior;

use Ody\DB\Migrations\Behavior\ParamsCheckerBehavior;
use Ody\DB\Migrations\Database\Element\MigrationTable;

trait CopyTableBehavior
{
    use ParamsCheckerBehavior;

    private ?string $newName = null;

    private string $copyType;

    public function getCopyType(): string
    {
        return $this->copyType;
    }

    public function copy(string $newName, string $copyType = MigrationTable::COPY_ONLY_STRUCTURE): void
    {
        $this->inArray($copyType, [MigrationTable::COPY_ONLY_STRUCTURE, MigrationTable::COPY_ONLY_DATA, MigrationTable::COPY_STRUCTURE_AND_DATA], 'Copy type "' . $copyType . '" is not allowed');

        $this->action = MigrationTable::ACTION_COPY;
        $this->newName = $newName;
        $this->copyType = $copyType;
    }
}
