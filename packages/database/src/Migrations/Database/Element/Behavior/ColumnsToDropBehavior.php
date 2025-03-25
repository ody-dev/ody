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

trait ColumnsToDropBehavior
{
    /** @var string[] */
    private array $columnsToDrop = [];

    public function dropColumn(string $name): MigrationTable
    {
        $this->columnsToDrop[] = $name;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getColumnsToDrop(): array
    {
        return $this->columnsToDrop;
    }
}
