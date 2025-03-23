<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Config\Parser;

use Nette\Neon\Neon;
use Ody\DB\Migrations\Exception\ConfigException;

final class NeonConfigParser implements ConfigParserInterface
{
    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new ConfigException('File "' . $filename . '" not found');
        }
        if (!class_exists('Nette\Neon\Neon')) {
            throw new ConfigException('Class Nette\Neon\Neon doesn\'t exist. Run composer require nette/neon');
        }
        $configString = str_replace('%%ACTUAL_DIR%%', pathinfo($filename, PATHINFO_DIRNAME), (string)file_get_contents($filename));
        return Neon::decode($configString);
    }
}
