<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ByteSizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('bytes', self::format(...))];
    }

    /** Decimal units (1 GB = 10^9 bytes), as storage providers bill. */
    public static function format(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1000 && $i < \count($units) - 1) {
            $value /= 1000;
            ++$i;
        }

        return 0 === $i ? $bytes.' B' : number_format($value, $value < 10 ? 2 : 1, ',', '.').' '.$units[$i];
    }
}
