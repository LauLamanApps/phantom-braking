<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\ValueObject\LightCondition;
use Doctrine\ORM\Mapping as ORM;

/**
 * Where the sun stood at the moment and place of an incident, calculated
 * (NOAA solar position algorithm, accurate to well under a degree). A low
 * sun in front of the camera is a known cause of phantom braking; this
 * makes "low sun" measurable instead of only the reporter's impression.
 */
#[ORM\Embeddable]
class SunPosition
{
    private function __construct(
        /** Degrees above the horizon; negative below it. */
        #[ORM\Column(nullable: true)]
        private ?float $elevation,
        /** Compass direction in degrees: 0 north, 90 east, 180 south. */
        #[ORM\Column(nullable: true)]
        private ?float $azimuth,
    ) {
    }

    public static function unknown(): self
    {
        return new self(null, null);
    }

    public static function at(float $latitude, float $longitude, \DateTimeImmutable $instant): self
    {
        $utc = $instant->setTimezone(new \DateTimeZone('UTC'));
        $minutes = (int) $utc->format('G') * 60 + (int) $utc->format('i') + (int) $utc->format('s') / 60;
        $julianDay = (int) $utc->format('U') / 86400 + 2440587.5;
        $t = ($julianDay - 2451545.0) / 36525; // Julian centuries since J2000

        $meanLongitude = fmod(280.46646 + $t * (36000.76983 + $t * 0.0003032), 360);
        $meanAnomaly = deg2rad(357.52911 + $t * (35999.05029 - 0.0001537 * $t));
        $eccentricity = 0.016708634 - $t * (0.000042037 + 0.0000001267 * $t);
        $centre = sin($meanAnomaly) * (1.914602 - $t * (0.004817 + 0.000014 * $t))
            + sin(2 * $meanAnomaly) * (0.019993 - 0.000101 * $t)
            + sin(3 * $meanAnomaly) * 0.000289;
        $omega = deg2rad(125.04 - 1934.136 * $t);
        $apparentLongitude = deg2rad($meanLongitude + $centre - 0.00569 - 0.00478 * sin($omega));
        $obliquity = deg2rad(23 + (26 + (21.448 - $t * (46.815 + $t * (0.00059 - $t * 0.001813))) / 60) / 60 + 0.00256 * cos($omega));
        $declination = asin(sin($obliquity) * sin($apparentLongitude));

        $y = tan($obliquity / 2) ** 2;
        $l0 = deg2rad($meanLongitude);
        $equationOfTime = 4 * rad2deg(
            $y * sin(2 * $l0) - 2 * $eccentricity * sin($meanAnomaly)
            + 4 * $eccentricity * $y * sin($meanAnomaly) * cos(2 * $l0)
            - 0.5 * $y * $y * sin(4 * $l0) - 1.25 * $eccentricity * $eccentricity * sin(2 * $meanAnomaly)
        );

        $trueSolarTime = fmod($minutes + $equationOfTime + 4 * $longitude + 1440, 1440);
        $hourAngle = deg2rad($trueSolarTime / 4 < 0 ? $trueSolarTime / 4 + 180 : $trueSolarTime / 4 - 180);
        $lat = deg2rad($latitude);

        $cosZenith = max(-1.0, min(1.0, sin($lat) * sin($declination) + cos($lat) * cos($declination) * cos($hourAngle)));
        $zenith = acos($cosZenith);
        $azimuth = rad2deg(atan2(sin($hourAngle), cos($hourAngle) * sin($lat) - tan($declination) * cos($lat))) + 180;

        return new self(round(90 - rad2deg($zenith), 1), round(fmod($azimuth, 360), 1));
    }

    public function isKnown(): bool
    {
        return null !== $this->elevation;
    }

    public function getElevation(): ?float
    {
        return $this->elevation;
    }

    public function getAzimuth(): ?float
    {
        return $this->azimuth;
    }

    /** Low enough to shine into a forward camera: above the horizon, below 15°. */
    public function isLow(): bool
    {
        return null !== $this->elevation && $this->elevation > 0 && $this->elevation < 15;
    }

    /**
     * The light the reporter most likely had, to pre-fill the form. A low sun behind a fully overcast sky
     * dazzles nobody, so that is daylight. After dark it can't tell street lighting, so it suggests nothing.
     */
    public function suggestedLight(?MeasuredWeather $weather = null): ?LightCondition
    {
        return match (true) {
            null === $this->elevation, $this->elevation < -6 => null,
            $this->elevation <= 0 => LightCondition::Twilight,
            $this->isLow() && ($weather?->getCloudCover() ?? 0) < 90 => LightCondition::LowSun,
            default => LightCondition::Daylight,
        };
    }
}
