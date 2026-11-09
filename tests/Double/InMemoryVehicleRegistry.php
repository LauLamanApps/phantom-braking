<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\Service\VehicleRegistryInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;

/** Replaces the RDW in tests so they don't hit the network. */
final class InMemoryVehicleRegistry implements VehicleRegistryInterface
{
    public function lookup(Country $country, LicensePlate $plate): VehicleSnapshot
    {
        return match ($plate->value) {
            'TE123S' => new VehicleSnapshot('TESLA', 'MODEL 3', new \DateTimeImmutable('2024-09-01'), 'Personenauto', 'M1', 'Elektriciteit', null, 'e4*2018/858*00217*07', 'MSA', null, false),
            'VW001X' => new VehicleSnapshot('VOLKSWAGEN', 'ID.3', new \DateTimeImmutable('2022-03-15'), 'Personenauto', 'M1', 'Elektriciteit', null, 'e1*2007/46*1934*12', 'E1', null, false),
            default => throw new VehicleNotFoundException('not found'),
        };
    }

    public function supportedCountries(): array
    {
        return [Country::Netherlands];
    }
}
