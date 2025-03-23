<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Config;

use Ody\DB\Migrations\Config\Parser\ConfigParserFactory;
use Ody\DB\Migrations\Config\Parser\JsonConfigParser;
use Ody\DB\Migrations\Config\Parser\NeonConfigParser;
use Ody\DB\Migrations\Config\Parser\PhpConfigParser;
use Ody\DB\Migrations\Config\Parser\YamlConfigParser;
use Ody\DB\Migrations\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

final class ConfigParserFactoryTest extends TestCase
{
    public function testInstance(): void
    {
        $this->assertInstanceOf(PhpConfigParser::class, ConfigParserFactory::instance('php'));
        $this->assertInstanceOf(PhpConfigParser::class, ConfigParserFactory::instance('PHP'));
        $this->assertInstanceOf(PhpConfigParser::class, ConfigParserFactory::instance('Php'));

        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('yml'));
        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('yaml'));
        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('YML'));
        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('YAML'));
        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('Yml'));
        $this->assertInstanceOf(YamlConfigParser::class, ConfigParserFactory::instance('Yaml'));

        $this->assertInstanceOf(NeonConfigParser::class, ConfigParserFactory::instance('neon'));
        $this->assertInstanceOf(NeonConfigParser::class, ConfigParserFactory::instance('NEON'));
        $this->assertInstanceOf(NeonConfigParser::class, ConfigParserFactory::instance('Neon'));

        $this->assertInstanceOf(JsonConfigParser::class, ConfigParserFactory::instance('json'));
        $this->assertInstanceOf(JsonConfigParser::class, ConfigParserFactory::instance('JSON'));
        $this->assertInstanceOf(JsonConfigParser::class, ConfigParserFactory::instance('Json'));
    }

    public function testUnknownType(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unknown config type "asdf"');
        ConfigParserFactory::instance('Asdf');
    }
}
