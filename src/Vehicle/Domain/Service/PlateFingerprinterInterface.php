<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\Service;

use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;

/**
 * Turns a plate into a stable, non-reversible fingerprint. Lets us count
 * distinct vehicles and repeat offenders without storing the plate itself
 * (a plate is personal data under the GDPR/AVG).
 */
interface PlateFingerprinterInterface
{
    public function fingerprint(Country $country, LicensePlate $plate): string;
}
