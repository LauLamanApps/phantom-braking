<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Vehicle\Domain\Exception\UnsupportedCountryException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\Service\CountryVehicleRegistryInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use App\Vehicle\Infrastructure\Service\CountryRoutingVehicleRegistry;
use PHPUnit\Framework\TestCase;

final class CountryRoutingVehicleRegistryTest extends TestCase
{
    public function testRoutesToTheRegistryOfTheCountry(): void
    {
        $dutch = new class implements CountryVehicleRegistryInterface {
            public function country(): Country
            {
                return Country::Netherlands;
            }

            public function lookup(LicensePlate $plate): VehicleSnapshot
            {
                return new VehicleSnapshot('FORD', 'PUMA');
            }
        };

        $registry = new CountryRoutingVehicleRegistry([$dutch]);

        self::assertSame([Country::Netherlands], $registry->supportedCountries());
        self::assertSame('PUMA', $registry->lookup(Country::Netherlands, LicensePlate::fromInput('GVN31K'))->getModel());
    }

    public function testRefusesCountriesWithoutRegistry(): void
    {
        $registry = new CountryRoutingVehicleRegistry([]);

        self::assertSame([], $registry->supportedCountries());
        $this->expectException(UnsupportedCountryException::class);
        $registry->lookup(Country::Netherlands, LicensePlate::fromInput('GVN31K'));
    }
}
