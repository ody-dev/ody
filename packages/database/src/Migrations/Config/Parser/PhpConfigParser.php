<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Config\Parser;

use Ody\DB\Migrations\Exception\ConfigException;

final class PhpConfigParser implements ConfigParserInterface
{
    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new ConfigException('File "' . $filename . '" not found');
        }
        return require $filename;
    }
}
