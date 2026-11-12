<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Model;

/**
 * Read model for the home page. Every distribution maps a raw key (enum
 * value, make, road number, ...) to a count, ordered for display.
 */
final readonly class StatisticsOverview
{
    /**
     * @param array<string, int>                                                    $byMonth              "YYYY-MM" → reports, gap-free over the selected period (default: last 12 months)
     * @param array<int, int>                                                       $byHour               0..23 → reports
     * @param array<int|string, int>                                                $byMake               top makes (a numeric key becomes an int in PHP)
     * @param list<array{make: string, model: string, reports: int, vehicles: int}> $topModels
     * @param array<int, int>                                                       $byFirstAdmissionYear
     * @param array<string, int>                                                    $bySpeed              bucket label → reports
     * @param list<array{country: string, road: string, reports: int}>              $topRoads             per incident country, as A7 in DE is not A7 in FR
     * @param array<string, array<int|string, int>>                                 $byEnum               dimension → enum value → reports
     * @param array<string, array<string, int>>                                     $byMeasured           sun / temperature / precipitation → bucket → reports
     * @param int                                                                   $measuredReports      reports with measured weather (the sun is known for every report with a location)
     */
    public function __construct(
        public Totals $totals,
        public array $byMonth,
        public array $byHour,
        public array $byMake,
        public array $topModels,
        public array $byFirstAdmissionYear,
        public array $bySpeed,
        public array $topRoads,
        public array $byEnum,
        public array $byMeasured = [],
        public int $measuredReports = 0,
    ) {
    }

    /** @return array<int|string, int> */
    public function dimension(string $name): array
    {
        return $this->byEnum[$name] ?? [];
    }

    /** @return array<string, int> */
    public function measured(string $name): array
    {
        return $this->byMeasured[$name] ?? [];
    }
}
