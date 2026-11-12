<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Model\MeasuredWeather;
use App\Incident\Domain\Service\WeatherHistoryInterface;

/** Replaces Open-Meteo in tests: a light rain shower at 4 °C, or an outage. */
final class FakeWeatherHistory implements WeatherHistoryInterface
{
    public bool $down = false;

    /** @var list<array{float, float, \DateTimeImmutable}> */
    public array $lookups = [];

    public function at(float $latitude, float $longitude, \DateTimeImmutable $instant): MeasuredWeather
    {
        if ($this->down) {
            throw new WeatherHistoryUnavailableException('Down for the test.');
        }
        $this->lookups[] = [$latitude, $longitude, $instant];

        return MeasuredWeather::measured(new \DateTimeImmutable(), 4.2, 0.6, 0.0, 85, 61, 9000, 18.0, 35.0, 92, 40.0);
    }
}
