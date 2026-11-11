<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Service;

use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Model\MapPoint;
use App\Statistics\Domain\Model\StatisticsOverview;

interface IncidentStatisticsInterface
{
    public function overview(IncidentFilter $filter, \DateTimeImmutable $now): StatisticsOverview;

    /**
     * The most recent reports with coordinates. The map clusters them client-side, so the cap is only
     * there to bound the response (about 35 bytes per point gzipped).
     */
    public const int MAP_POINTS_LIMIT = 20000;

    /** @return list<MapPoint> */
    public function mapPoints(IncidentFilter $filter, int $limit = self::MAP_POINTS_LIMIT): array;
}
