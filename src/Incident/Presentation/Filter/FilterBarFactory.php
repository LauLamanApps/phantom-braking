<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Filter;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\RoadType;
use App\Vehicle\Domain\ValueObject\Country;

/** Everything the filter-bar template needs: the current state plus the available options. */
final readonly class FilterBarFactory
{
    public function __construct(private IncidentRepositoryInterface $incidents)
    {
    }

    /**
     * Countries are the incident countries that have reports, so any European country can show up.
     * `map` centres the map on the selected (or only) country when we know its outline.
     *
     * @return array{query: IncidentFilterQuery, countries: list<string>, scopeCountry: ?string, map: array{centre: array{float, float}, zoom: int}, makes: array<int|string, int>, models: array<int|string, int>, roadTypes: list<RoadType>, periods: list<Period>}
     */
    public function create(IncidentFilterQuery $query, \DateTimeImmutable $now): array
    {
        $filter = $query->toFilter($now);
        $countries = array_map('strval', array_keys($this->incidents->countByCountry($filter)));
        $scope = $query->country->code ?? (1 === \count($countries) ? $countries[0] : null);
        $known = null === $scope ? null : Country::tryFrom($scope);

        return [
            'query' => $query,
            'countries' => $countries,
            'scopeCountry' => $scope,
            'map' => null === $known ? ['centre' => [49.5, 6.5], 'zoom' => 5] : ['centre' => $known->centre(), 'zoom' => $known->mapZoom()],
            'makes' => $this->incidents->countByMake($filter),
            'models' => $this->incidents->countByModel($filter),
            'roadTypes' => RoadType::cases(),
            'periods' => Period::cases(),
        ];
    }
}
