<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** JSON body of a map-click lookup. POST, so coordinates stay out of URLs and access logs. */
final readonly class RoadLookupRequest
{
    public function __construct(
        #[Assert\Range(min: -90, max: 90)]
        public float $latitude,
        #[Assert\Range(min: -180, max: 180)]
        public float $longitude,
    ) {
    }
}
