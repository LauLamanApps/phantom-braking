<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** What the road looked like at the exact spot. Viaducts, tunnels and gantries are classic radar/camera false-positive triggers. */
enum RoadSituation: string implements TranslatableEnum
{
    case StraightRoad = 'straight';
    case Curve = 'curve';
    case TunnelEntrance = 'tunnel_entrance';
    case InsideTunnel = 'tunnel_inside';
    case TunnelExit = 'tunnel_exit';
    case UnderViaduct = 'under_viaduct';
    case Bridge = 'bridge';
    case SignGantry = 'sign_gantry';
    case OnRamp = 'on_ramp';
    case OffRamp = 'off_ramp';
    case LaneMerge = 'lane_merge';
    case Roadworks = 'roadworks';
    case Intersection = 'intersection';
    case Roundabout = 'roundabout';
    case RailwayCrossing = 'railway_crossing';
    case Hill = 'hill';

    public function translationKey(): string
    {
        return 'enum.road_situation.'.$this->value;
    }
}
