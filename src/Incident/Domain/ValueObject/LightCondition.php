<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

enum LightCondition: string implements TranslatableEnum
{
    case Daylight = 'daylight';
    case LowSun = 'low_sun';
    case Twilight = 'twilight';
    case DarkLit = 'dark_lit';
    case DarkUnlit = 'dark_unlit';

    public function translationKey(): string
    {
        return 'enum.light.'.$this->value;
    }
}
