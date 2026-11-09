<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/**
 * Which reports a list or statistic covers. Every criterion is optional;
 * an empty filter means "all reports from all countries". The country is
 * where the incident happened, not where the vehicle is registered.
 */
final readonly class IncidentFilter
{
    public ?string $make;
    public ?string $model;

    public function __construct(
        public ?IncidentCountry $country = null,
        ?string $make = null,
        ?string $model = null,
        public ?RoadType $roadType = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
        $this->make = self::normalise($make);
        // A model only means something within a make.
        $this->model = null === $this->make ? null : self::normalise($model);

        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException('The start of the period must be before its end.');
        }
    }

    public static function all(): self
    {
        return new self();
    }

    /** Same filter without the make/model criteria, e.g. to list which makes are available. */
    public function withoutVehicle(): self
    {
        return new self($this->country, null, null, $this->roadType, $this->from, $this->to);
    }

    public function withoutCountry(): self
    {
        return new self(null, $this->make, $this->model, $this->roadType, $this->from, $this->to);
    }

    public function withoutModel(): self
    {
        return new self($this->country, $this->make, null, $this->roadType, $this->from, $this->to);
    }

    private static function normalise(?string $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));

        return '' === $value ? null : $value;
    }
}
