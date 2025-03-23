<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Helpers\Command;

use Symfony\Component\Console\Formatter\NullOutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;

final class Formatter implements OutputFormatterInterface
{
    private bool $isDecorated = false;

    public function format(?string $message): ?string
    {
        return $message;
    }

    public function getStyle(string $name): OutputFormatterStyleInterface
    {
        return new NullOutputFormatterStyle();
    }

    public function hasStyle(string $name): bool
    {
        return false;
    }

    public function isDecorated(): bool
    {
        return $this->isDecorated;
    }

    public function setDecorated(bool $decorated): void
    {
        $this->isDecorated = $decorated;
    }

    public function setStyle(string $name, OutputFormatterStyleInterface $style): void
    {
    }
}
