<?php

declare(strict_types=1);

namespace App\Vehicle\Infrastructure\Service;

use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Exception\VehicleRegistryUnavailableException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\Service\CountryVehicleRegistryInterface;
use App\Vehicle\Domain\Service\PlateFingerprinterInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RDW open data (Socrata). Datasets:
 *  - m9d7-ebf2  Gekentekende voertuigen
 *  - 8ys7-d773  Gekentekende voertuigen brandstof
 *
 * @see https://opendata.rdw.nl
 */
final readonly class RdwVehicleRegistry implements CountryVehicleRegistryInterface
{
    private const string VEHICLES = '/resource/m9d7-ebf2.json';
    private const string FUEL = '/resource/8ys7-d773.json';

    public function __construct(
        private HttpClientInterface $rdwClient,
        private CacheInterface $cache,
        private PlateFingerprinterInterface $fingerprinter,
    ) {
    }

    public function country(): Country
    {
        return Country::Netherlands;
    }

    public function lookup(LicensePlate $plate): VehicleSnapshot
    {
        // Keyed fingerprint, not the plate: the cache must not become a store of plates.
        $cacheKey = 'rdw_'.$this->fingerprinter->fingerprint(Country::Netherlands, $plate);
        /** @var array{vehicle: array<string, string>, fuel: list<array<string, string>>}|null $data */
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($plate): ?array {
            $item->expiresAfter(86400);

            $vehicle = $this->query(self::VEHICLES, $plate)[0] ?? null;
            if (null === $vehicle) {
                $item->expiresAfter(600);

                return null;
            }

            return ['vehicle' => $vehicle, 'fuel' => $this->query(self::FUEL, $plate)];
        });

        if (null === $data) {
            throw new VehicleNotFoundException('No vehicle registered with this plate.');
        }

        return $this->map($data['vehicle'], $data['fuel']);
    }

    /** @return list<array<string, string>> */
    private function query(string $dataset, LicensePlate $plate): array
    {
        try {
            $rows = $this->rdwClient->request('GET', $dataset, [
                'query' => ['kenteken' => $plate->value],
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw new VehicleRegistryUnavailableException('RDW open data is unavailable.', previous: $e);
        }

        // Socrata returns every value as a string; keep only scalar fields of row objects,
        // and drop the plate itself so it is never cached.
        $result = [];
        foreach ($rows as $row) {
            if (\is_array($row)) {
                unset($row['kenteken']);
                $result[] = array_map(strval(...), array_filter($row, is_scalar(...)));
            }
        }

        return $result;
    }

    /**
     * @param array<string, string>       $vehicle
     * @param list<array<string, string>> $fuel
     */
    private function map(array $vehicle, array $fuel): VehicleSnapshot
    {
        usort($fuel, static fn (array $a, array $b): int => ($a['brandstof_volgnummer'] ?? 0) <=> ($b['brandstof_volgnummer'] ?? 0));
        $fuels = array_values(array_filter(array_map(static fn (array $f): ?string => $f['brandstof_omschrijving'] ?? null, $fuel)));
        $hybridClass = array_values(array_filter(array_map(static fn (array $f): ?string => $f['klasse_hybride_elektrisch_voertuig'] ?? null, $fuel)))[0] ?? null;

        $firstAdmission = isset($vehicle['datum_eerste_toelating'])
            ? \DateTimeImmutable::createFromFormat('!Ymd', $vehicle['datum_eerste_toelating']) ?: null
            : null;

        return new VehicleSnapshot(
            make: $vehicle['merk'] ?? 'ONBEKEND',
            model: $vehicle['handelsbenaming'] ?? 'ONBEKEND',
            firstAdmission: $firstAdmission,
            vehicleKind: $vehicle['voertuigsoort'] ?? null,
            europeanCategory: $vehicle['europese_voertuigcategorie'] ?? null,
            fuel: [] === $fuels ? null : implode(', ', $fuels),
            hybridClass: $hybridClass,
            typeApprovalNumber: $vehicle['typegoedkeuringsnummer'] ?? null,
            variant: $vehicle['variant'] ?? null,
            execution: $vehicle['uitvoering'] ?? null,
            openRecall: isset($vehicle['openstaande_terugroepactie_indicator']) ? 'Ja' === $vehicle['openstaande_terugroepactie_indicator'] : null,
        );
    }
}
