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

final class IndexColumn
{
    private string $name;

    private IndexColumnSettings $columnSettings;

    /**
     * @param array<string, int|string> $columnSettings
     */
    public function __construct(string $name, array $columnSettings = [])
    {
        $this->name = $name;
        $this->columnSettings = new IndexColumnSettings($columnSettings);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSettings(): IndexColumnSettings
    {
        return $this->columnSettings;
    }
}
