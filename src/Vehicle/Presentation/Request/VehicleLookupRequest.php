<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation\Request;

use App\Vehicle\Domain\ValueObject\Country;
use Symfony\Component\Validator\Constraints as Assert;

/** JSON body of a live lookup. Sent as POST so plates never appear in URLs or access logs. */
final readonly class VehicleLookupRequest
{
    public function __construct(
        public Country $country,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public string $plate,
    ) {
    }
}
