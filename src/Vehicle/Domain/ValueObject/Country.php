<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/**
 * Countries the platform knows about (statistics, filters, maps).
 *
 * Knowing a country is not the same as accepting reports from it: a report
 * can only be filed for a country with a national-registry integration (a
 * CountryVehicleRegistryInterface), so make, model and year always come
 * from an authoritative source. See VehicleRegistryInterface::supportedCountries().
 */
enum Country: string implements TranslatableEnum
{
    case Netherlands = 'NL';
    case Germany = 'DE';
    case France = 'FR';

    public function translationKey(): string
    {
        return 'country.'.strtolower($this->value);
    }

    /** @return array{float, float} rough geographic centre, for map views */
    public function centre(): array
    {
        return match ($this) {
            self::Netherlands => [52.2, 5.3],
            self::Germany => [51.2, 10.4],
            self::France => [46.6, 2.4],
        };
    }

    public function mapZoom(): int
    {
        return match ($this) {
            self::Netherlands => 7,
            self::Germany, self::France => 6,
        };
    }
}
