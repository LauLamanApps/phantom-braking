<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\Service;

use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Exception\VehicleRegistryUnavailableException;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;

/**
 * One national vehicle registry. Implement this (in Infrastructure) to
 * support a new country; it is picked up automatically.
 */
interface CountryVehicleRegistryInterface
{
    public function country(): Country;

    /**
     * @throws VehicleNotFoundException
     * @throws VehicleRegistryUnavailableException
     */
    public function lookup(LicensePlate $plate): VehicleSnapshot;
}
