<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Request;

use App\Incident\Domain\ValueObject\IncidentCountry;
use Symfony\Component\Validator\Constraints as Assert;

/** JSON body of the pre-fill lookup: where and when, as the reporter entered it (local time where it happened). */
final readonly class ConditionsLookupRequest
{
    public function __construct(
        #[Assert\Range(min: -90, max: 90)]
        public float $latitude,
        #[Assert\Range(min: -180, max: 180)]
        public float $longitude,
        #[Assert\DateTime(format: 'Y-m-d\TH:i')]
        public string $occurredAt,
        #[Assert\Choice(choices: IncidentCountry::EUROPE)]
        public string $country,
    ) {
    }
}
