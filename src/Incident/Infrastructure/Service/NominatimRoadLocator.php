<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Exception\RoadLocatorUnavailableException;
use App\Incident\Domain\Model\Location;
use App\Incident\Domain\Service\RoadAtLocation;
use App\Incident\Domain\Service\RoadLocatorInterface;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reverse geocoding with OpenStreetMap Nominatim. Zoom 16 ("major streets")
 * prefers the motorway over a cycle path or a street running alongside it.
 *
 * Usage policy (https://operations.osmfoundation.org/policies/nominatim/):
 * at most 1 request per second for the whole application, an identifying
 * User-Agent (set on the scoped client), and caching of results.
 */
final readonly class NominatimRoadLocator implements RoadLocatorInterface
{
    private const int CACHE_TTL = 30 * 86400;

    public function __construct(
        private HttpClientInterface $nominatimClient,
        private CacheInterface $cache,
        private RateLimiterFactoryInterface $nominatimLimiter,
    ) {
    }

    public function locate(float $latitude, float $longitude): RoadAtLocation
    {
        // Same ~11 m precision we store; also makes nearby clicks share a cache entry.
        $latitude = round($latitude, 4);
        $longitude = round($longitude, 4);

        /** @var array<string, mixed> $data */
        $data = $this->cache->get(sprintf('nominatim_%s_%s', $latitude, $longitude), function (ItemInterface $item) use ($latitude, $longitude): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->fetch($latitude, $longitude);
        });

        return self::map($data);
    }

    /** @return array<string, mixed> */
    private function fetch(float $latitude, float $longitude): array
    {
        try {
            // One request per second for the whole site; wait up to 3 s for our turn.
            $this->nominatimLimiter->create('global')->reserve(1, 3.0)->wait();
        } catch (MaxWaitDurationExceededException $e) {
            throw new RoadLocatorUnavailableException('Too many map lookups right now.', previous: $e);
        }

        try {
            $data = $this->nominatimClient->request('GET', '/reverse', ['query' => [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'jsonv2',
                'zoom' => 16,
                'addressdetails' => 1,
                'namedetails' => 1,
                'extratags' => 1,
            ]])->toArray();
        } catch (ExceptionInterface $e) {
            throw new RoadLocatorUnavailableException('OpenStreetMap Nominatim is unavailable.', previous: $e);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public static function map(array $data): RoadAtLocation
    {
        $address = self::strings($data['address'] ?? []);
        $tags = self::strings($data['extratags'] ?? []);
        $names = self::strings($data['namedetails'] ?? []);
        $country = IncidentCountry::tryFromString($address['country_code'] ?? null);

        if ('highway' !== ($data['category'] ?? null)) {
            return new RoadAtLocation(country: $country);
        }

        $ref = self::nationalRef($names['ref'] ?? null);
        $type = \is_string($data['type'] ?? null) ? $data['type'] : '';
        $builtUp = isset($address['city']) || isset($address['town']) || isset($address['suburb']);

        return new RoadAtLocation(
            country: $country,
            roadNumber: $ref,
            roadType: self::roadType($type, null !== $ref, $builtUp),
            situation: match (true) {
                \in_array($tags['tunnel'] ?? null, ['yes', 'building_passage', 'avalanche_protector'], true) => RoadSituation::InsideTunnel,
                \in_array($tags['bridge'] ?? null, ['yes', 'viaduct'], true) => RoadSituation::Bridge,
                default => null, // a *_link could be an on- or an off-ramp: leave it to the reporter
            },
            speedLimit: isset($tags['maxspeed']) && ctype_digit($tags['maxspeed']) ? (int) $tags['maxspeed'] : null,
            name: isset($data['name']) && \is_string($data['name']) && '' !== $data['name'] ? $data['name'] : null,
        );
    }

    /** "A 6" → "A6"; "A2;E25" → "A2" (the national number, not the European route). */
    private static function nationalRef(?string $ref): ?string
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $refs = array_values(array_filter(array_map(static fn (string $r): ?string => Location::normaliseRoadNumber($r), explode(';', $ref))));
        $national = array_values(array_filter($refs, static fn (string $r): bool => 1 !== preg_match('/^E\d/', $r)));

        return $national[0] ?? $refs[0] ?? null;
    }

    /** OSM highway class → our road type. A best guess; the reporter can correct it. */
    private static function roadType(string $highway, bool $hasRef, bool $builtUp): ?RoadType
    {
        return match (preg_replace('/_link$/', '', $highway)) {
            'motorway' => RoadType::Motorway,
            'trunk' => RoadType::Expressway,
            'primary', 'secondary' => $hasRef ? RoadType::ProvincialRoad : ($builtUp ? RoadType::UrbanRoad : RoadType::RuralRoad),
            'tertiary', 'unclassified', 'road' => $builtUp ? RoadType::UrbanRoad : RoadType::RuralRoad,
            'residential', 'living_street', 'service' => RoadType::ParkingOrResidential,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $key => $value) {
            if (\is_string($key) && \is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }
}
