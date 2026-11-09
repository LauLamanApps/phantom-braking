<?php

declare(strict_types=1);

namespace App\Vehicle\Infrastructure\Service;

use App\Vehicle\Domain\Exception\UnsupportedCountryException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\Service\CountryVehicleRegistryInterface;
use App\Vehicle\Domain\Service\VehicleRegistryInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Sends each lookup to the registry of the plate's country. */
final readonly class CountryRoutingVehicleRegistry implements VehicleRegistryInterface
{
    /** @var array<string, CountryVehicleRegistryInterface> */
    private array $registries;

    /** @param iterable<CountryVehicleRegistryInterface> $registries */
    public function __construct(
        #[AutowireIterator('app.country_vehicle_registry')]
        iterable $registries,
    ) {
        $byCountry = [];
        foreach ($registries as $registry) {
            $byCountry[$registry->country()->value] = $registry;
        }
        $this->registries = $byCountry;
    }

    public function lookup(Country $country, LicensePlate $plate): VehicleSnapshot
    {
        $registry = $this->registries[$country->value]
            ?? throw new UnsupportedCountryException(sprintf('No vehicle registry for %s.', $country->value));

        return $registry->lookup($plate);
    }

    public function supportedCountries(): array
    {
        $supported = [];
        foreach (Country::cases() as $country) {
            if (isset($this->registries[$country->value])) {
                $supported[] = $country;
            }
        }

        return $supported;
    }
}
