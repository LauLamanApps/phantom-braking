<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** How often this vehicle has done this before. */
enum Recurrence: string implements TranslatableEnum
{
    case FirstTime = 'first';
    case Few = 'few';
    case Regular = 'regular';
    case Frequent = 'frequent';

    public function translationKey(): string
    {
        return 'enum.recurrence.'.$this->value;
    }
}
