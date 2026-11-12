<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\Command\RecordMeasuredConditionsCommand;
use App\Incident\Domain\CommandHandler\RecordMeasuredConditionsCommandHandler;
use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Model\Location;
use App\Incident\Domain\Model\MeasuredWeather;
use App\Incident\Domain\Model\SunPosition;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Incident\Infrastructure\Service\OpenMeteoWeatherHistory;
use App\Tests\Double\FakeWeatherHistory;
use App\Tests\Double\InMemoryIncidentRepository;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Country;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MeasuredConditionsTest extends TestCase
{
    public function testSunAtNoonOnTheSummerSolstice(): void
    {
        // 90° − 52° latitude + 23.44° tilt; due south.
        $sun = SunPosition::at(52.0, 0.0, new \DateTimeImmutable('2025-06-21 12:02', new \DateTimeZone('UTC')));

        self::assertEqualsWithDelta(61.4, $sun->getElevation(), 0.3);
        self::assertEqualsWithDelta(180.0, $sun->getAzimuth(), 2.0);
        self::assertFalse($sun->isLow());
    }

    public function testLowMorningSunInTheSouthEastAndNightBelowTheHorizon(): void
    {
        // Utrecht, half an hour after sunrise in November.
        $morning = SunPosition::at(52.09, 5.12, new \DateTimeImmutable('2026-11-12 08:30', new \DateTimeZone('Europe/Amsterdam')));
        self::assertEqualsWithDelta(3.8, $morning->getElevation(), 0.5);
        self::assertEqualsWithDelta(126.0, $morning->getAzimuth(), 2.0);
        self::assertTrue($morning->isLow());
        self::assertSame(LightCondition::LowSun, $morning->suggestedLight());
        // Behind a fully overcast sky a low sun dazzles nobody.
        self::assertSame(LightCondition::Daylight, $morning->suggestedLight(MeasuredWeather::measured(new \DateTimeImmutable(), 5, 0, 0, 100, 3)));

        $night = SunPosition::at(52.09, 5.12, new \DateTimeImmutable('2026-11-12 23:00', new \DateTimeZone('Europe/Amsterdam')));
        self::assertLessThan(-30, $night->getElevation());
        self::assertNull($night->suggestedLight(), 'after dark it cannot tell whether the road was lit');

        $dusk = SunPosition::at(52.09, 5.12, new \DateTimeImmutable('2026-11-12 17:05', new \DateTimeZone('Europe/Amsterdam')));
        self::assertSame(LightCondition::Twilight, $dusk->suggestedLight());
    }

    public function testEveryIncidentCountryHasItsMainlandTimezone(): void
    {
        foreach (IncidentCountry::EUROPE as $code) {
            self::assertNotSame('UTC', IncidentCountry::fromString($code)->timezone()->getName(), $code);
        }
        self::assertSame('Asia/Nicosia', IncidentCountry::fromString('CY')->timezone()->getName());
        self::assertSame('Atlantic/Reykjavik', IncidentCountry::fromString('IS')->timezone()->getName());
        self::assertSame('Europe/Kyiv', IncidentCountry::fromString('UA')->timezone()->getName());
        self::assertSame('Europe/Madrid', IncidentCountry::fromString('ES')->timezone()->getName());
        self::assertSame('Europe/Lisbon', IncidentCountry::fromString('PT')->timezone()->getName());
    }

    public function testReportedLocalTimeIsReadInTheCountryWhereItHappened(): void
    {
        $dutch = self::incident(new \DateTimeImmutable('2026-07-01 14:00'), 'NL', 52.09, 5.12);
        $portuguese = self::incident(new \DateTimeImmutable('2026-07-01 14:00'), 'PT', 38.72, -9.14);

        self::assertSame('2026-07-01T12:00:00+00:00', $dutch->getOccurredInstant()->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        self::assertSame('2026-07-01T13:00:00+00:00', $portuguese->getOccurredInstant()->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        // The hour chart keeps counting the local hour.
        self::assertSame(14, (int) $dutch->getOccurredAt()->format('G'));
    }

    public function testSunIsCalculatedOnReportingAndWeatherIsLookedUpOnce(): void
    {
        $incident = self::incident(new \DateTimeImmutable('2026-07-01 14:00'), 'NL', 52.09, 5.12);
        self::assertGreaterThan(55, $incident->getSun()->getElevation());
        self::assertTrue($incident->needsMeasuredWeather());

        $incident->recordMeasuredWeather(MeasuredWeather::unavailable(new \DateTimeImmutable()));
        self::assertFalse($incident->needsMeasuredWeather(), 'no record for that hour is still an answer');

        $withoutLocation = self::incident(new \DateTimeImmutable('2026-07-01 14:00'), 'NL', null, null);
        self::assertFalse($withoutLocation->getSun()->isKnown());
        self::assertFalse($withoutLocation->needsMeasuredWeather());
    }

    public function testSuggestedWeatherFollowsWhatFellInThatHour(): void
    {
        $at = new \DateTimeImmutable();
        $suggest = static fn (float $precipitation, float $snow = 0, int $code = 3, ?int $visibility = 20000): ?Weather => MeasuredWeather::measured($at, 5, $precipitation, $snow, 80, $code, $visibility)->suggestedWeather();

        self::assertSame(Weather::Dry, $suggest(0.0, code: 61), 'a rain code for the area, but nothing fell here');
        self::assertSame(Weather::Rain, $suggest(0.4));
        self::assertSame(Weather::HeavyRain, $suggest(6.0));
        self::assertSame(Weather::Snow, $suggest(1.0, 0.7, 73));
        self::assertSame(Weather::Fog, $suggest(0.0, code: 45));
        self::assertSame(Weather::Fog, $suggest(0.0, visibility: 300));
        self::assertNull(MeasuredWeather::unavailable($at)->suggestedWeather());
    }

    public function testOpenMeteoGetsARoundedPointAndTheHourOnly(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse(json_encode(['hourly' => [
                'time' => ['2026-11-12T07:00'], 'temperature_2m' => [3.9], 'precipitation' => [0.0], 'snowfall' => [0.0],
                'cloud_cover' => [3], 'weather_code' => [0], 'visibility' => [12600.0], 'wind_speed_10m' => [7.9],
                'wind_gusts_10m' => [11.5], 'relative_humidity_2m' => [85], 'shortwave_radiation' => [44.0],
            ]], \JSON_THROW_ON_ERROR));
        }, 'https://historical-forecast-api.open-meteo.com');
        $history = new OpenMeteoWeatherHistory($client, new ArrayAdapter());

        $weather = $history->at(52.09071, 5.12142, new \DateTimeImmutable('2026-11-12 08:30', new \DateTimeZone('Europe/Amsterdam')));
        // Same area and hour: answered from the cache.
        $history->at(52.1123, 5.0811, new \DateTimeImmutable('2026-11-12 08:59', new \DateTimeZone('Europe/Amsterdam')));

        self::assertCount(1, $requests);
        parse_str((string) parse_url($requests[0], \PHP_URL_QUERY), $query);
        self::assertSame(['52.1', '5.1', '2026-11-12T07:00', '2026-11-12T07:00'], [$query['latitude'], $query['longitude'], $query['start_hour'], $query['end_hour']]);
        self::assertSame(3.9, $weather->getTemperature());
        self::assertSame(12600, $weather->getVisibility());
        self::assertSame(85, $weather->getHumidity());
        self::assertSame(Weather::Dry, $weather->suggestedWeather());
    }

    public function testAnHourOutsideTheArchiveIsUnavailableButAnOutageIsAnError(): void
    {
        $outsideArchive = new OpenMeteoWeatherHistory(new MockHttpClient(new MockResponse('{"error":true,"reason":"out of range"}', ['http_code' => 400])), new ArrayAdapter());
        $weather = $outsideArchive->at(52.1, 5.1, new \DateTimeImmutable('2019-01-01 12:00'));
        self::assertTrue($weather->isChecked());
        self::assertFalse($weather->isAvailable());

        $down = new OpenMeteoWeatherHistory(new MockHttpClient(new MockResponse('', ['http_code' => 503])), new ArrayAdapter());
        $this->expectException(WeatherHistoryUnavailableException::class);
        $down->at(52.1, 5.1, new \DateTimeImmutable('2026-01-01 12:00'));
    }

    public function testHandlerRecordsTheMeasuredWeatherAtTheRealInstant(): void
    {
        $incidents = new InMemoryIncidentRepository();
        $incident = self::incident(new \DateTimeImmutable('2026-07-01 14:00'), 'NL', 52.09, 5.12);
        $incidents->persist($incident);
        $incidents->flush();
        $weather = new FakeWeatherHistory();

        $handler = new RecordMeasuredConditionsCommandHandler($incidents, $weather);
        $handler(new RecordMeasuredConditionsCommand($incident->getId()));
        $handler(new RecordMeasuredConditionsCommand($incident->getId()));

        self::assertCount(1, $weather->lookups, 'looked up once');
        self::assertSame('2026-07-01T12:00:00+00:00', $weather->lookups[0][2]->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        self::assertSame(4.2, $incident->getMeasuredWeather()->getTemperature());
        self::assertSame([], $incidents->getIdsNeedingMeasuredConditions(10));
    }

    private static function incident(\DateTimeImmutable $occurredAt, string $country, ?float $latitude, ?float $longitude): Incident
    {
        return new Incident(
            id: IncidentId::generate(),
            country: Country::Netherlands,
            plateFingerprint: str_repeat('a', 64),
            vehicle: new VehicleSnapshot('MAZDA', 'CX-60', new \DateTimeImmutable('2024-10-01')),
            occurredAt: $occurredAt,
            location: new Location(IncidentCountry::fromString($country), RoadType::Motorway, RoadSituation::UnderViaduct, null, $latitude, $longitude),
            severity: BrakeSeverity::Emergency,
            speedBefore: 100,
            speedAfter: 40,
            trigger: SuspectedTrigger::ViaductOrGantry,
            assistSystem: ActiveAssistSystem::AdaptiveCruise,
            warningShown: true,
            weather: Weather::Dry,
            light: LightCondition::Daylight,
            consequence: Consequence::NearRearEnd,
            recurrence: Recurrence::Few,
            dealerResponse: DealerResponse::NoFaultFound,
            reportedToAuthority: false,
            windscreenReplacedRecently: null,
            odometerKm: null,
            softwareVersion: null,
            description: null,
            reportedAt: new \DateTimeImmutable('2026-11-12'),
        );
    }
}
