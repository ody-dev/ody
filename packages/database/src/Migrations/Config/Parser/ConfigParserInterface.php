<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Config\Parser;

interface ConfigParserInterface
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $filename): array;
}
