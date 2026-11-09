<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\Service;

use App\Vehicle\Domain\Exception\UnsupportedCountryException;
use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Exception\VehicleRegistryUnavailableException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;

interface VehicleRegistryInterface
{
    /**
     * @throws UnsupportedCountryException
     * @throws VehicleNotFoundException
     * @throws VehicleRegistryUnavailableException
     */
    public function lookup(Country $country, LicensePlate $plate): VehicleSnapshot;

    /** @return list<Country> countries we can look plates up for */
    public function supportedCountries(): array;
}
