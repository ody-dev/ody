<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Comparator;

use Ody\DB\Migrations\Database\Element\Column;

final class ColumnComparator
{
    public function diff(Column $sourceColumn, Column $targetColumn): ?Column
    {
        $sourceName = $sourceColumn->getName();
        $targetName = $targetColumn->getName();

        $sourceType = $sourceColumn->getType();
        $targetType = $targetColumn->getType();

        $settingsComparator = new SettingsComparator();
        $settings = $settingsComparator->diff($sourceColumn->getSettings(), $targetColumn->getSettings());

        if ($sourceName === $targetName && $sourceType === $targetType && empty($settings)) {
            return null;
        }

        return new Column($targetName, $targetType, $settings);
    }
}
