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

use Ody\DB\Migrations\Database\Element\MigrationTable;

trait DropPrimaryKeyBehavior
{
    private bool $dropPrimaryKey = false;

    public function dropPrimaryKey(): MigrationTable
    {
        $this->dropPrimaryKey = true;
        return $this;
    }

    public function hasPrimaryKeyToDrop(): bool
    {
        return $this->dropPrimaryKey;
    }
}
