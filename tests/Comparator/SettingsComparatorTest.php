<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Comparator;

use Ody\DB\Migrations\Comparator\SettingsComparator;
use Ody\DB\Migrations\Database\Element\ColumnSettings;
use PHPUnit\Framework\TestCase;

final class SettingsComparatorTest extends TestCase
{
    public function testSameEmpty(): void
    {
        $settings1 = new ColumnSettings();
        $settings2 = new ColumnSettings();
        $settingsComparator = new SettingsComparator();
        $this->assertEquals([], $settingsComparator->diff($settings1, $settings2));
    }

    public function testSameNonEmpty(): void
    {
        $settings1 = new ColumnSettings(['null' => true, 'default' => 10]);
        $settings2 = new ColumnSettings(['null' => true, 'default' => 10]);
        $settingsComparator = new SettingsComparator();
        $this->assertEquals([], $settingsComparator->diff($settings1, $settings2));
    }

    public function testSameEnumValues(): void
    {
        $settings1 = new ColumnSettings(['values' => ['a', 'b', 'c']]);
        $settings2 = new ColumnSettings(['values' => ['a', 'b', 'c']]);
        $settingsComparator = new SettingsComparator();
        $this->assertEquals([], $settingsComparator->diff($settings1, $settings2));
    }

    public function testSomeDifferentEnumValues(): void
    {
        $settings1 = new ColumnSettings(['values' => ['a', 'b', 'c']]);
        $settings2 = new ColumnSettings(['values' => ['b', 'c', 'd']]);
        $settingsComparator = new SettingsComparator();
        $this->assertEquals(['values' => ['b', 'c', 'd']], $settingsComparator->diff($settings1, $settings2));
    }

    public function testCompletelyDifferentEnumValues(): void
    {
        $settings1 = new ColumnSettings(['values' => ['a', 'b', 'c']]);
        $settings2 = new ColumnSettings(['values' => ['d', 'e', 'f']]);
        $settingsComparator = new SettingsComparator();
        $this->assertEquals(['values' => ['d', 'e', 'f']], $settingsComparator->diff($settings1, $settings2));
    }
}
