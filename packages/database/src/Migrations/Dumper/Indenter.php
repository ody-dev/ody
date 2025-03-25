<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Dumper;

final class Indenter
{
    public function indent(string $identifier = '4spaces'): string
    {
        $indent = strtolower(str_replace([' ', '-', '_'], '', $identifier));
        if ($indent === '2spaces') {
            return '  ';
        }
        if ($indent === '3spaces') {
            return '   ';
        }
        if ($indent === '5spaces') {
            return '     ';
        }
        if ($indent === 'tab') {
            return "\t";
        }
        return '    ';
    }
}
