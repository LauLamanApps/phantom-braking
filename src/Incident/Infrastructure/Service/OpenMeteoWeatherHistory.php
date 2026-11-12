<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Model\MeasuredWeather;
use App\Incident\Domain\Service\WeatherHistoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Open-Meteo's Historical Forecast API: hourly weather from 2021 up to the
 * current hour, without an API key (free for non-commercial use; the data
 * is CC BY 4.0, so pages showing it credit Open-Meteo).
 *
 * Only a point rounded to 0.1° (about 10 km, as coarse as the weather grid
 * anyway) and the hour are sent, never anything about the report. The
 * pre-fill lookup and the lookup after the report share a cache entry.
 */
final readonly class OpenMeteoWeatherHistory implements WeatherHistoryInterface
{
    private const string FIELDS = 'temperature_2m,precipitation,snowfall,cloud_cover,weather_code,visibility,wind_speed_10m,wind_gusts_10m,relative_humidity_2m,shortwave_radiation';

    public function __construct(
        private HttpClientInterface $openMeteoClient,
        private CacheInterface $cache,
    ) {
    }

    public function at(float $latitude, float $longitude, \DateTimeImmutable $instant): MeasuredWeather
    {
        $latitude = round($latitude, 1);
        $longitude = round($longitude, 1);
        $hour = $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:00');

        /** @var array<string, mixed> $data */
        $data = $this->cache->get(sprintf('open_meteo_%s_%s_%s', $latitude, $longitude, str_replace(':', '', $hour)), function (ItemInterface $item) use ($latitude, $longitude, $hour): array {
            $data = $this->fetch($latitude, $longitude, $hour);
            // A recorded hour doesn't change; an hour not recorded yet may be soon.
            $item->expiresAfter(null === self::first($data, 'temperature_2m') ? 600 : 7 * 86400);

            return $data;
        });

        return self::map($data, new \DateTimeImmutable());
    }

    /** @return array<string, mixed> */
    private function fetch(float $latitude, float $longitude, string $hour): array
    {
        try {
            // An hour outside the archive (before 2021, or still to come) is a 400 with a reason: no data, not an outage.
            $response = $this->openMeteoClient->request('GET', '/v1/forecast', ['query' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hourly' => self::FIELDS,
                'start_hour' => $hour,
                'end_hour' => $hour,
                'timezone' => 'GMT',
            ]]);
            if ($response->getStatusCode() >= 500) {
                throw new WeatherHistoryUnavailableException(sprintf('Open-Meteo answered %d.', $response->getStatusCode()));
            }

            return $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new WeatherHistoryUnavailableException('Open-Meteo is unavailable.', previous: $e);
        }
    }

    /** @param array<string, mixed> $data */
    public static function map(array $data, \DateTimeImmutable $checkedAt): MeasuredWeather
    {
        $number = static fn (string $key): ?float => is_numeric($value = self::first($data, $key)) ? (float) $value : null;
        $integer = static fn (string $key): ?int => null === ($value = $number($key)) ? null : (int) round($value);

        $temperature = $number('temperature_2m');
        $cloudCover = $integer('cloud_cover');
        $code = $integer('weather_code');
        if (null === $temperature || null === $cloudCover || null === $code) {
            return MeasuredWeather::unavailable($checkedAt);
        }

        $humidity = $integer('relative_humidity_2m');

        return MeasuredWeather::measured(
            checkedAt: $checkedAt,
            temperature: $temperature,
            precipitation: max(0.0, $number('precipitation') ?? 0.0),
            snowfall: max(0.0, $number('snowfall') ?? 0.0),
            cloudCover: max(0, min(100, $cloudCover)),
            weatherCode: $code,
            visibility: $integer('visibility'),
            windSpeed: $number('wind_speed_10m'),
            windGusts: $number('wind_gusts_10m'),
            humidity: null === $humidity ? null : max(0, min(100, $humidity)),
            solarRadiation: $number('shortwave_radiation'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function first(array $data, string $key): mixed
    {
        $hourly = \is_array($data['hourly'] ?? null) ? $data['hourly'] : [];

        return \is_array($hourly[$key] ?? null) ? ($hourly[$key][0] ?? null) : null;
    }
}
