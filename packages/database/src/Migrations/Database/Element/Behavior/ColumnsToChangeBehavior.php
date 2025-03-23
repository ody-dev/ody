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

use Ody\DB\Migrations\Database\Element\Column;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;

trait ColumnsToChangeBehavior
{
    /** @var array<string, Column> */
    private array $columns = [];

    /** @var array<string, Column> */
    private array $columnsToChange = [];

    /**
     * @param array{null?: bool, default?: mixed, length?: int, decimals?: int, signed?: bool, autoincrement?: bool, after?: string, first?: bool, charset?: string, collation?: string, values?: array<int|string, int|string>, comment?: string} $settings
     * @throws InvalidArgumentValueException
     */
    public function changeColumn(string $oldName, string $newName, string $type, array $settings = []): MigrationTable
    {
        $newColumn = new Column($newName, $type, $settings);
        if (isset($this->columns[$oldName])) {
            $this->columns[$oldName] = $newColumn;
            return $this;
        }

        $this->columnsToChange[$oldName] = $newColumn;
        return $this;
    }

    /**
     * @return array<string, Column>
     */
    public function getColumnsToChange(): array
    {
        return $this->columnsToChange;
    }
}
