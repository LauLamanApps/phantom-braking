<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Model;

final readonly class MapPoint
{
    public function __construct(
        public string $incidentId,
        public float $latitude,
        public float $longitude,
        public string $make,
        public string $model,
        public string $situation,
        public string $severity,
        public bool $collision,
    ) {
    }
}
