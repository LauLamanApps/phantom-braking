<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

enum Weather: string implements TranslatableEnum
{
    case Dry = 'dry';
    case Rain = 'rain';
    case HeavyRain = 'heavy_rain';
    case Fog = 'fog';
    case Snow = 'snow';

    public function translationKey(): string
    {
        return 'enum.weather.'.$this->value;
    }
}
