<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Service\RoadAtLocation;
use App\Incident\Domain\Service\RoadLocatorInterface;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;

/** Replaces Nominatim in tests: the Coentunnel, or nothing. */
final class FakeRoadLocator implements RoadLocatorInterface
{
    public function locate(float $latitude, float $longitude): RoadAtLocation
    {
        if (abs($latitude - 52.4137) < 0.01) {
            return new RoadAtLocation(IncidentCountry::fromString('NL'), 'A10', RoadType::Motorway, RoadSituation::InsideTunnel, 100, 'Coentunnel');
        }

        return new RoadAtLocation();
    }
}
