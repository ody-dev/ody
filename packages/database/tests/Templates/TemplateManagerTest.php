<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Templates;

use Ody\DB\Migrations\Exception\PhoenixException;
use Ody\DB\Migrations\Migration\MigrationNameCreator;
use Ody\DB\Migrations\Templates\TemplateManager;
use PHPUnit\Framework\TestCase;

final class TemplateManagerTest extends TestCase
{
    public function testTemplatePathNotFound(): void
    {
        $this->expectException(PhoenixException::class);
        $this->expectExceptionMessage('Template "this-file-doesnt-exist" not found');
        new TemplateManager(new MigrationNameCreator('\Abc\Def'), '    ', 'this-file-doesnt-exist');
    }

    public function testEmptyMigrationWithoutNamespace(): void
    {
        $templateManager = new TemplateManager(new MigrationNameCreator('Def'), '    ');

        $expected = <<<MIGRATION
<?php

declare(strict_types=1);

use Ody\DB\Migrations\Migration\AbstractMigration;

final class Def extends AbstractMigration
{
    protected function up(): void
    {

    }

    protected function down(): void
    {

    }
}

MIGRATION;

        $this->assertEquals($expected, $templateManager->createMigrationFromTemplate('', ''));
    }

    public function testEmptyWithNamespaceAndSpecialIndent(): void
    {
        $templateManager = new TemplateManager(new MigrationNameCreator('\Abc\Def'), 'asdf');

        $expected = <<<MIGRATION
<?php

declare(strict_types=1);

namespace Abc;

use Ody\DB\Migrations\Migration\AbstractMigration;

final class Def extends AbstractMigration
{
asdfprotected function up(): void
asdf{

asdf}

asdfprotected function down(): void
asdf{

asdf}
}

MIGRATION;

        $this->assertEquals($expected, $templateManager->createMigrationFromTemplate('', ''));
    }
}
