<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** Type of road the incident happened on. */
enum RoadType: string implements TranslatableEnum
{
    case Motorway = 'motorway';
    case Expressway = 'expressway';
    case ProvincialRoad = 'provincial';
    case UrbanRoad = 'urban';
    case RuralRoad = 'rural';
    case ParkingOrResidential = 'residential';

    public function translationKey(): string
    {
        return 'enum.road_type.'.$this->value;
    }
}
