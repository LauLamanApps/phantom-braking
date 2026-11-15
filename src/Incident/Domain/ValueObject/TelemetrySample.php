<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/** What the car recorded at one moment of its dashcam footage. */
final readonly class TelemetrySample
{
    public ?float $latitude;
    public ?float $longitude;

    /**
     * @param float      $time                     seconds into the video, as the player counts them
     * @param float      $speed                    km/h
     * @param float|null $acceleratorPedal         % pressed
     * @param float|null $steeringAngle            steering wheel angle in degrees, positive is to the right
     * @param float|null $heading                  degrees from north, clockwise
     * @param float|null $longitudinalAcceleration m/s², positive is speeding up, negative is braking
     * @param float|null $lateralAcceleration      m/s², positive is to the right
     */
    public function __construct(
        public float $time,
        public float $speed,
        public ?Gear $gear = null,
        public ?float $acceleratorPedal = null,
        public ?float $steeringAngle = null,
        public bool $blinkerLeft = false,
        public bool $blinkerRight = false,
        public bool $brakePedal = false,
        public DrivingAutomation $automation = DrivingAutomation::None,
        ?float $latitude = null,
        ?float $longitude = null,
        public ?float $heading = null,
        public ?float $longitudinalAcceleration = null,
        public ?float $lateralAcceleration = null,
    ) {
        $known = null !== $latitude && null !== $longitude && (0.0 !== $latitude || 0.0 !== $longitude)
            && abs($latitude) <= 90 && abs($longitude) <= 180;
        // Same ~11 m precision as the report's own location: the footage is published, a precise track is not.
        $this->latitude = $known ? round($latitude, 4) : null;
        $this->longitude = $known ? round((float) $longitude, 4) : null;
    }

    /** Whether something a viewer watches for (pedal, blinkers, gear, assistance) differs from $other. */
    public function changedStateSince(self $other): bool
    {
        return $this->brakePedal !== $other->brakePedal
            || $this->blinkerLeft !== $other->blinkerLeft
            || $this->blinkerRight !== $other->blinkerRight
            || $this->gear !== $other->gear
            || $this->automation !== $other->automation;
    }

    /** @return array<string, float|bool|string|null> */
    public function toArray(): array
    {
        return [
            't' => round($this->time, 3),
            'speed' => round($this->speed, 1),
            'gear' => $this->gear?->value,
            'accelerator' => null === $this->acceleratorPedal ? null : round($this->acceleratorPedal, 1),
            'steering' => null === $this->steeringAngle ? null : round($this->steeringAngle, 1),
            'blinkerLeft' => $this->blinkerLeft,
            'blinkerRight' => $this->blinkerRight,
            'brake' => $this->brakePedal,
            'automation' => $this->automation->value,
            'lat' => $this->latitude,
            'lon' => $this->longitude,
            'heading' => null === $this->heading ? null : round($this->heading, 1),
            'accelLong' => null === $this->longitudinalAcceleration ? null : round($this->longitudinalAcceleration, 2),
            'accelLat' => null === $this->lateralAcceleration ? null : round($this->lateralAcceleration, 2),
        ];
    }
}
