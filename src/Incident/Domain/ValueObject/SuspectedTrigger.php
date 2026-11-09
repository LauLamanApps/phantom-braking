<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** What the reporter thinks set the car off. */
enum SuspectedTrigger: string implements TranslatableEnum
{
    case NothingVisible = 'nothing_visible';
    case Shadow = 'shadow';
    case ViaductOrGantry = 'viaduct_gantry';
    case OncomingTraffic = 'oncoming';
    case TruckAdjacentLane = 'truck_adjacent';
    case VehicleAdjacentLane = 'vehicle_adjacent';
    case MergingVehicle = 'merging';
    case TurningVehicleAhead = 'turning_ahead';
    case ParkedVehicle = 'parked';
    case RoadSign = 'road_sign';
    case Guardrail = 'guardrail';
    case RoadMarking = 'road_marking';
    case LowSunOrReflection = 'low_sun';
    case RainOrSpray = 'rain_spray';
    case Other = 'other';

    public function translationKey(): string
    {
        return 'enum.trigger.'.$this->value;
    }
}
