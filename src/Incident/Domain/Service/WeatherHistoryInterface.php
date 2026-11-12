<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Model\MeasuredWeather;

interface WeatherHistoryInterface
{
    /**
     * The recorded weather for the hour of the instant, around the point.
     * Unavailable (not an error) when there is no record for that hour.
     *
     * @throws WeatherHistoryUnavailableException when the service cannot be reached; worth trying again later
     */
    public function at(float $latitude, float $longitude, \DateTimeImmutable $instant): MeasuredWeather;
}
