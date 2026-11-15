<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

enum Gear: string
{
    case Park = 'P';
    case Reverse = 'R';
    case Neutral = 'N';
    case Drive = 'D';
}
