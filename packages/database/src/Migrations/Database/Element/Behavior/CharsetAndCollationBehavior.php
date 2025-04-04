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

trait CharsetAndCollationBehavior
{
    private ?string $charset = null;

    private ?string $collation = null;

    public function setCharset(?string $charset): MigrationTable
    {
        $this->charset = $charset;
        return $this;
    }

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    public function setCollation(?string $collation): MigrationTable
    {
        $this->collation = $collation;
        return $this;
    }

    public function getCollation(): ?string
    {
        return $this->collation;
    }
}
