<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Service;

use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Model\MapPoint;
use App\Statistics\Domain\Model\StatisticsOverview;

interface IncidentStatisticsInterface
{
    public function overview(IncidentFilter $filter, \DateTimeImmutable $now): StatisticsOverview;

    /** @return list<MapPoint> */
    public function mapPoints(IncidentFilter $filter, int $limit = 2000): array;
}
