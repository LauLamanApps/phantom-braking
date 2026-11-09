<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;

/** What the map knows about the road at a point. Every field is a suggestion and may be unknown. */
final readonly class RoadAtLocation
{
    public function __construct(
        public ?IncidentCountry $country = null,
        public ?string $roadNumber = null,
        public ?RoadType $roadType = null,
        public ?RoadSituation $situation = null,
        public ?int $speedLimit = null,
        public ?string $name = null,
    ) {
    }
}
