<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Filter;

use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\RoadType;

/**
 * The filter as it lives in the URL (?country=NL&make=TESLA&period=90d…).
 * Shared by the dashboard, the reports list and the map API so a filtered
 * view can be bookmarked and moved between pages.
 */
final readonly class IncidentFilterQuery
{
    public function __construct(
        public ?IncidentCountry $country = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?RoadType $roadType = null,
        public Period $period = Period::All,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }

    /**
     * Lenient: unknown or malformed values are ignored rather than failing the page.
     *
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $string = static fn (string $key): ?string => isset($query[$key]) && \is_string($query[$key]) && '' !== trim($query[$key]) ? trim($query[$key]) : null;
        $date = static function (?string $value): ?\DateTimeImmutable {
            $date = null === $value ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return false === $date ? null : $date;
        };

        $from = $date($string('from'));
        $to = $date($string('to'));
        $period = Period::tryFrom($string('period') ?? '') ?? (null !== $from || null !== $to ? Period::Custom : Period::All);
        if (null !== $from && null !== $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $make = $string('make');

        return new self(
            country: IncidentCountry::tryFromString($string('country')),
            make: null === $make ? null : mb_strtoupper($make),
            model: null === $make || null === $string('model') ? null : mb_strtoupper((string) $string('model')),
            roadType: RoadType::tryFrom($string('road_type') ?? ''),
            period: $period,
            from: Period::Custom === $period ? $from : null,
            to: Period::Custom === $period ? $to : null,
        );
    }

    public function toFilter(\DateTimeImmutable $now): IncidentFilter
    {
        [$from, $to] = $this->period->range($now, $this->from, $this->to);

        return new IncidentFilter($this->country, $this->make, $this->model, $this->roadType, $from, $to);
    }

    /** @return array<string, string> only the criteria that are set, for building links */
    public function toQuery(): array
    {
        return array_filter([
            'country' => $this->country?->code,
            'make' => $this->make,
            'model' => $this->model,
            'road_type' => $this->roadType?->value,
            'period' => Period::All === $this->period ? null : $this->period->value,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
        ], static fn (?string $v): bool => null !== $v);
    }

    public function isActive(): bool
    {
        return [] !== $this->toQuery();
    }
}
