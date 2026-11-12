<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\ValueObject\Weather;
use Doctrine\ORM\Mapping as ORM;

/**
 * The weather a weather model recorded for the hour and area (about 10 km)
 * of an incident, next to what the reporter said. Looked up after the
 * report; `checkedAt` is set even when nothing was recorded for that hour,
 * so a report is only looked up once.
 */
#[ORM\Embeddable]
class MeasuredWeather
{
    private function __construct(
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $checkedAt,
        /** °C at 2 m. Set whenever the lookup found the hour. */
        #[ORM\Column(nullable: true)]
        private ?float $temperature = null,
        /** mm of rain, showers and snow (as water) in that hour. */
        #[ORM\Column(nullable: true)]
        private ?float $precipitation = null,
        /** cm of fresh snow in that hour. */
        #[ORM\Column(nullable: true)]
        private ?float $snowfall = null,
        /** % of the sky covered. */
        #[ORM\Column(nullable: true)]
        private ?int $cloudCover = null,
        /** WMO weather code, e.g. 0 clear, 45 fog, 61 light rain, 71 snow. */
        #[ORM\Column(nullable: true)]
        private ?int $weatherCode = null,
        /** Metres; not recorded for hours before mid-2023. */
        #[ORM\Column(nullable: true)]
        private ?int $visibility = null,
        /** km/h at 10 m. */
        #[ORM\Column(nullable: true)]
        private ?float $windSpeed = null,
        #[ORM\Column(nullable: true)]
        private ?float $windGusts = null,
        /** % relative humidity at 2 m: high humidity near freezing fogs up or ices sensors. */
        #[ORM\Column(nullable: true)]
        private ?int $humidity = null,
        /** W/m² of sunlight on a flat surface: how bright the sun really was, behind clouds or not. */
        #[ORM\Column(nullable: true)]
        private ?float $solarRadiation = null,
    ) {
    }

    public static function notChecked(): self
    {
        return new self(null);
    }

    public static function unavailable(\DateTimeImmutable $checkedAt): self
    {
        return new self($checkedAt);
    }

    public static function measured(
        \DateTimeImmutable $checkedAt,
        float $temperature,
        float $precipitation,
        float $snowfall,
        int $cloudCover,
        int $weatherCode,
        ?int $visibility = null,
        ?float $windSpeed = null,
        ?float $windGusts = null,
        ?int $humidity = null,
        ?float $solarRadiation = null,
    ): self {
        if ($precipitation < 0 || $snowfall < 0 || $cloudCover < 0 || $cloudCover > 100 || ($humidity ?? 0) < 0 || ($humidity ?? 0) > 100) {
            throw new \InvalidArgumentException('Measured weather out of range.');
        }

        $round = static fn (?float $value): ?float => null === $value ? null : round($value, 1);

        return new self($checkedAt, round($temperature, 1), round($precipitation, 1), round($snowfall, 1), $cloudCover, $weatherCode, $visibility, $round($windSpeed), $round($windGusts), $humidity, $round($solarRadiation));
    }

    /**
     * What the reporter most likely saw, to pre-fill the form. Precipitation in that hour decides over the
     * weather code, which also says "rain" for a shower elsewhere in the area.
     */
    public function suggestedWeather(): ?Weather
    {
        if (null === $this->temperature) {
            return null;
        }

        return match (true) {
            \in_array($this->weatherCode, [45, 48], true) || (null !== $this->visibility && $this->visibility < 1000) => Weather::Fog,
            $this->snowfall > 0 || \in_array($this->weatherCode, [71, 73, 75, 77, 85, 86], true) => Weather::Snow,
            $this->precipitation >= 4 => Weather::HeavyRain,
            $this->precipitation >= 0.1 => Weather::Rain,
            default => Weather::Dry,
        };
    }

    public function isChecked(): bool
    {
        return null !== $this->checkedAt;
    }

    public function isAvailable(): bool
    {
        return null !== $this->temperature;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getPrecipitation(): ?float
    {
        return $this->precipitation;
    }

    public function getSnowfall(): ?float
    {
        return $this->snowfall;
    }

    public function getCloudCover(): ?int
    {
        return $this->cloudCover;
    }

    public function getWeatherCode(): ?int
    {
        return $this->weatherCode;
    }

    public function getVisibility(): ?int
    {
        return $this->visibility;
    }

    public function getWindSpeed(): ?float
    {
        return $this->windSpeed;
    }

    public function getWindGusts(): ?float
    {
        return $this->windGusts;
    }

    public function getHumidity(): ?int
    {
        return $this->humidity;
    }

    public function getSolarRadiation(): ?float
    {
        return $this->solarRadiation;
    }
}
