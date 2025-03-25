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

use Ody\DB\Migrations\Database\Element\ForeignKey;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;

trait ForeignKeyBehavior
{
    /** @var ForeignKey[] */
    private array $foreignKeys = [];

    /** @var string[] */
    private array $foreignKeysToDrop = [];

    /**
     * @param string|string[] $columns
     * @param string|string[] $referencedColumns
     * @throws InvalidArgumentValueException
     */
    public function addForeignKey($columns, string $referencedTable, $referencedColumns = ['id'], string $onDelete = ForeignKey::DEFAULT_ACTION, string $onUpdate = ForeignKey::DEFAULT_ACTION): MigrationTable
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        if (!is_array($referencedColumns)) {
            $referencedColumns = [$referencedColumns];
        }
        $this->foreignKeys[] = new ForeignKey($columns, $referencedTable, $referencedColumns, $onDelete, $onUpdate);
        return $this;
    }

    /**
     * @return ForeignKey[]
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @param string|string[] $columns
     */
    public function dropForeignKey($columns): MigrationTable
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        $this->foreignKeysToDrop[] = implode('_', $columns);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getForeignKeysToDrop(): array
    {
        return $this->foreignKeysToDrop;
    }
}
