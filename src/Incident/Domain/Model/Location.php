<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use Doctrine\ORM\Mapping as ORM;

/** Where the incident happened. Its country is not the vehicle's registration country. */
#[ORM\Embeddable]
class Location
{
    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $roadNumber;

    public function __construct(
        IncidentCountry $country,
        #[ORM\Column(enumType: RoadType::class)]
        private RoadType $roadType,
        #[ORM\Column(enumType: RoadSituation::class)]
        private RoadSituation $situation,
        ?string $roadNumber = null,
        #[ORM\Column(nullable: true)]
        private ?float $latitude = null,
        #[ORM\Column(nullable: true)]
        private ?float $longitude = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $direction = null,
        #[ORM\Column(nullable: true)]
        private ?int $speedLimit = null,
    ) {
        if ((null === $latitude) !== (null === $longitude)) {
            throw new \InvalidArgumentException('Latitude and longitude must be given together.');
        }
        if (null !== $latitude && (abs($latitude) > 90 || abs((float) $longitude) > 180)) {
            throw new \InvalidArgumentException('Coordinates out of range.');
        }

        $this->country = $country->code;
        $this->roadNumber = self::normaliseRoadNumber($roadNumber);
        // ~11 m precision is plenty to find hotspots and avoids false precision.
        $this->latitude = null === $latitude ? null : round($latitude, 4);
        $this->longitude = null === $longitude ? null : round((float) $longitude, 4);
        $this->direction = null === $direction || '' === trim($direction) ? null : trim($direction);
    }

    /** "a 15", "A-15", "a15" → "A15"; "n325" → "N325". */
    public static function normaliseRoadNumber(?string $roadNumber): ?string
    {
        $value = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $roadNumber));

        return '' === $value ? null : $value;
    }

    public function getCountry(): IncidentCountry
    {
        return IncidentCountry::fromString($this->country);
    }

    public function getRoadType(): RoadType
    {
        return $this->roadType;
    }

    public function getSituation(): RoadSituation
    {
        return $this->situation;
    }

    public function getRoadNumber(): ?string
    {
        return $this->roadNumber;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function hasCoordinates(): bool
    {
        return null !== $this->latitude;
    }

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function getSpeedLimit(): ?int
    {
        return $this->speedLimit;
    }
}
