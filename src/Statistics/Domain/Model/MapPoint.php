<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Model;

/** One report on the statistics map: only what the map and its popup need, as there can be thousands. */
final readonly class MapPoint
{
    public function __construct(
        public string $incidentId,
        public float $latitude,
        public float $longitude,
        public string $make,
        public string $model,
        public bool $collision,
    ) {
    }
}
