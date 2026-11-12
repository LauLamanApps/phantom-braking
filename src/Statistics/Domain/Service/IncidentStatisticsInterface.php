<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Service;

use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Model\MapPoint;
use App\Statistics\Domain\Model\StatisticsOverview;

interface IncidentStatisticsInterface
{
    public function overview(IncidentFilter $filter, \DateTimeImmutable $now): StatisticsOverview;

    /** @return list<array{make: string, model: string, reports: int, vehicles: int}> every model with reports, most reported first */
    public function vehicleModels(IncidentFilter $filter): array;

    /** @return array<int|string, int> software version as reported → reports, most reported first (a numeric version becomes an int key) */
    public function softwareVersions(IncidentFilter $filter, int $limit = 10): array;

    /**
     * The most recent reports with coordinates. The map clusters them client-side, so the cap is only
     * there to bound the response (about 35 bytes per point gzipped).
     */
    public const int MAP_POINTS_LIMIT = 20000;

    /** @return list<MapPoint> */
    public function mapPoints(IncidentFilter $filter, int $limit = self::MAP_POINTS_LIMIT): array;
}
