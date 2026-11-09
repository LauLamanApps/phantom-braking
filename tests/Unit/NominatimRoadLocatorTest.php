<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\Exception\RoadLocatorUnavailableException;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Infrastructure\Service\NominatimRoadLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Response shapes are trimmed copies of real Nominatim answers (November 2026). */
final class NominatimRoadLocatorTest extends TestCase
{
    public function testMotorwayInATunnel(): void
    {
        $road = NominatimRoadLocator::map([
            'category' => 'highway', 'type' => 'motorway', 'name' => 'Coentunnel',
            'address' => ['city' => 'Amsterdam', 'country_code' => 'nl'],
            'namedetails' => ['name' => 'Coentunnel', 'ref' => 'A10'],
            'extratags' => ['layer' => '-1', 'tunnel' => 'yes', 'maxspeed' => '100'],
        ]);

        self::assertSame('NL', $road->country?->code);
        self::assertSame('A10', $road->roadNumber);
        self::assertSame(RoadType::Motorway, $road->roadType);
        self::assertSame(RoadSituation::InsideTunnel, $road->situation);
        self::assertSame(100, $road->speedLimit);
        self::assertSame('Coentunnel', $road->name);
    }

    public function testGermanAutobahnWithoutSpeedLimitAndSpacedRef(): void
    {
        $road = NominatimRoadLocator::map([
            'category' => 'highway', 'type' => 'motorway', 'name' => 'A 6',
            'address' => ['country_code' => 'de'],
            'namedetails' => ['ref' => 'A 6;E 50'],
            'extratags' => ['lanes' => '2', 'maxspeed' => 'none'],
        ]);

        self::assertSame('DE', $road->country?->code);
        self::assertSame('A6', $road->roadNumber, 'national number, normalised, not the E-route');
        self::assertNull($road->speedLimit, '"none" is not a number');
        self::assertNull($road->situation);
    }

    public function testRoadTypeGuesses(): void
    {
        $type = static fn (string $highway, array $address = [], ?string $ref = null): ?RoadType => NominatimRoadLocator::map([
            'category' => 'highway', 'type' => $highway, 'address' => $address + ['country_code' => 'nl'], 'namedetails' => null === $ref ? [] : ['ref' => $ref],
        ])->roadType;

        self::assertSame(RoadType::Expressway, $type('trunk'));
        self::assertSame(RoadType::Motorway, $type('motorway_link'));
        self::assertSame(RoadType::ProvincialRoad, $type('secondary', ref: 'N325'));
        self::assertSame(RoadType::UrbanRoad, $type('tertiary', ['city' => 'Utrecht']));
        self::assertSame(RoadType::RuralRoad, $type('unclassified', ['village' => 'Bemmel']));
        self::assertSame(RoadType::ParkingOrResidential, $type('residential', ['town' => 'Vleuten']));
        self::assertNull($type('cycleway'));
    }

    public function testNotOnARoadStillGivesTheCountry(): void
    {
        $road = NominatimRoadLocator::map(['category' => 'place', 'type' => 'hamlet', 'address' => ['country_code' => 'fr']]);

        self::assertSame('FR', $road->country?->code);
        self::assertNull($road->roadNumber);
        self::assertNull($road->roadType);
    }

    public function testOutsideEuropeHasNoCountry(): void
    {
        self::assertNull(NominatimRoadLocator::map(['category' => 'highway', 'type' => 'motorway', 'address' => ['country_code' => 'us']])->country);
    }

    public function testQueriesNominatimOnceForNearbyClicksAndSendsTheRightParameters(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse((string) json_encode(['category' => 'highway', 'type' => 'motorway', 'address' => ['country_code' => 'nl'], 'namedetails' => ['ref' => 'A2']]));
        }, 'https://nominatim.openstreetmap.org');

        $locator = new NominatimRoadLocator($client, new ArrayAdapter(), self::unlimited());

        self::assertSame('A2', $locator->locate(52.056457, 5.066057)->roadNumber);
        self::assertSame('A2', $locator->locate(52.056461, 5.066071)->roadNumber, 'same ~11 m cell: served from cache');
        self::assertCount(1, $requests);
        self::assertStringContainsString('/reverse?', $requests[0]);
        self::assertStringContainsString('zoom=16', $requests[0]);
        self::assertStringContainsString('lat=52.0565', $requests[0]);
    }

    public function testUnavailableWhenNominatimFails(): void
    {
        $locator = new NominatimRoadLocator(new MockHttpClient(new MockResponse('', ['http_code' => 503])), new ArrayAdapter(), self::unlimited());

        $this->expectException(RoadLocatorUnavailableException::class);
        $locator->locate(52.0, 5.0);
    }

    private static function unlimited(): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'nominatim', 'policy' => 'no_limit'], new InMemoryStorage());
    }
}
