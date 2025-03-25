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

trait AutoIncrementBehavior
{
    private ?int $autoIncrement = null;

    public function setAutoIncrement(?int $autoIncrement): self
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    public function getAutoIncrement(): ?int
    {
        return $this->autoIncrement;
    }
}
