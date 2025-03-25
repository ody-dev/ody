<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\Element;

use Ody\DB\Migrations\Behavior\ParamsCheckerBehavior;

final class UniqueConstraint
{
    use ParamsCheckerBehavior;

    /** @var string[] */
    private array $columns;

    private string $name;

    /**
     * @param string[] $columns
     * @param string $name
     */
    public function __construct(array $columns, string $name)
    {
        $this->columns = $columns;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
