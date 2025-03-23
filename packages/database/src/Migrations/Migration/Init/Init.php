<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Migration\Init;

use Ody\DB\Migrations\Database\Adapter\AdapterInterface;
use Ody\DB\Migrations\Database\Element\Column;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;
use Ody\DB\Migrations\Migration\AbstractMigration;

final class Init extends AbstractMigration
{
    private string $logTableName;

    public function __construct(AdapterInterface $adapter, string $logTableName)
    {
        parent::__construct($adapter);
        $this->logTableName = $logTableName;
    }

    /**
     * @throws InvalidArgumentValueException
     */
    protected function up(): void
    {
        $this->table($this->logTableName)
            ->addColumn('migration_datetime', Column::TYPE_STRING)
            ->addColumn('classname', Column::TYPE_STRING)
            ->addColumn('executed_at', Column::TYPE_DATETIME)
            ->create();
    }

    protected function down(): void
    {
        $this->table($this->logTableName)
            ->drop();
    }
}
