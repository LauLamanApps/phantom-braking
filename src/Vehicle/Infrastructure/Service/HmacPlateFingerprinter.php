<?php

declare(strict_types=1);

namespace App\Vehicle\Infrastructure\Service;

use App\Vehicle\Domain\Service\PlateFingerprinterInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class HmacPlateFingerprinter implements PlateFingerprinterInterface
{
    public function __construct(
        #[Autowire('%env(PLATE_FINGERPRINT_KEY)%')]
        private string $key,
    ) {
    }

    public function fingerprint(Country $country, LicensePlate $plate): string
    {
        return hash_hmac('sha256', $country->value.':'.$plate->value, $this->key);
    }
}
