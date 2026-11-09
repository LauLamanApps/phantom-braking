<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\Exception\RoadLocatorUnavailableException;

/** Looks up the road at a map point, to pre-fill the report form. */
interface RoadLocatorInterface
{
    /** @throws RoadLocatorUnavailableException */
    public function locate(float $latitude, float $longitude): RoadAtLocation;
}
