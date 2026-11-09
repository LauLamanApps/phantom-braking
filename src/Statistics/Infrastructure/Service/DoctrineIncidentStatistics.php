<?php

declare(strict_types=1);

namespace App\Statistics\Infrastructure\Service;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Infrastructure\Repository\IncidentFilterApplier;
use App\Statistics\Domain\Model\MapPoint;
use App\Statistics\Domain\Model\StatisticsOverview;
use App\Statistics\Domain\Model\Totals;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineIncidentStatistics implements IncidentStatisticsInterface
{
    /** Enum-backed columns we break down by, keyed by dimension name. */
    private const array DIMENSIONS = [
        'road_type' => 'i.location.roadType',
        'road_situation' => 'i.location.situation',
        'trigger' => 'i.trigger',
        'severity' => 'i.severity',
        'light' => 'i.light',
        'weather' => 'i.weather',
        'assist' => 'i.assistSystem',
        'consequence' => 'i.consequence',
        'recurrence' => 'i.recurrence',
        'dealer' => 'i.dealerResponse',
    ];

    private const array SPEED_BUCKETS = [0, 30, 50, 80, 100, 120, 131];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function overview(IncidentFilter $filter, \DateTimeImmutable $now): StatisticsOverview
    {
        $byEnum = [];
        foreach (self::DIMENSIONS as $name => $field) {
            $byEnum[$name] = $this->groupCount($filter, $field);
        }

        return new StatisticsOverview(
            totals: $this->totals($filter),
            byMonth: $this->byMonth($filter, $now),
            byHour: $this->byHour($filter),
            byMake: $this->groupCount($filter, 'i.vehicle.make', 10),
            topModels: $this->topModels($filter),
            byFirstAdmissionYear: $this->byFirstAdmissionYear($filter),
            bySpeed: $this->bySpeed($filter),
            topRoads: $this->topRoads($filter),
            byEnum: $byEnum,
        );
    }

    public function mapPoints(IncidentFilter $filter, int $limit = 2000): array
    {
        $rows = $this->base($filter)
            ->select('i.id AS id, i.location.latitude AS lat, i.location.longitude AS lng, i.vehicle.make AS make, i.vehicle.model AS model, i.location.situation AS situation, i.severity AS severity, i.consequence AS consequence')
            ->andWhere('i.location.latitude IS NOT NULL')
            ->orderBy('i.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): MapPoint => new MapPoint(
            incidentId: (string) $r['id'],
            latitude: (float) $r['lat'],
            longitude: (float) $r['lng'],
            make: $r['make'],
            model: $r['model'],
            situation: $r['situation'] instanceof RoadSituation ? $r['situation']->value : (string) $r['situation'],
            severity: $r['severity'] instanceof BrakeSeverity ? $r['severity']->value : (string) $r['severity'],
            collision: ($r['consequence'] instanceof Consequence ? $r['consequence'] : Consequence::from((string) $r['consequence']))->isCollision(),
        ), $rows));
    }

    private function totals(IncidentFilter $filter): Totals
    {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $e): string => (string) $e->value, $cases);

        $row = $this->base($filter)
            ->select(
                'COUNT(i.id) AS reports',
                'COUNT(DISTINCT i.plateFingerprint) AS vehicles',
                'COUNT(DISTINCT i.vehicle.make) AS makes',
                'SUM(CASE WHEN i.severity <> :warningOnly THEN 1 ELSE 0 END) AS actuallyBraked',
                'SUM(CASE WHEN i.severity = :emergency THEN 1 ELSE 0 END) AS emergencyStops',
                'SUM(CASE WHEN i.consequence IN (:endangered) THEN 1 ELSE 0 END) AS endangeredFollower',
                'SUM(CASE WHEN i.consequence IN (:collisions) THEN 1 ELSE 0 END) AS collisions',
                'SUM(CASE WHEN i.location.roadType IN (:fastRoads) THEN 1 ELSE 0 END) AS onMotorway',
                'SUM(CASE WHEN i.location.situation IN (:infrastructure) THEN 1 ELSE 0 END) AS atInfrastructure',
                'SUM(CASE WHEN i.recurrence <> :firstTime THEN 1 ELSE 0 END) AS recurring',
                'SUM(CASE WHEN i.dealerResponse <> :notContacted THEN 1 ELSE 0 END) AS dealerContacted',
                'SUM(CASE WHEN i.dealerResponse IN (:dismissive) THEN 1 ELSE 0 END) AS dealerDismissive',
                'SUM(CASE WHEN i.reportedToAuthority = true THEN 1 ELSE 0 END) AS reportedToAuthority',
                'SUM(CASE WHEN i.footage.path IS NOT NULL THEN 1 ELSE 0 END) AS withFootage',
                'AVG(i.speedBefore) AS averageSpeedBefore',
                'AVG(i.speedBefore - i.speedAfter) AS averageSpeedDrop',
            )
            ->setParameter('warningOnly', BrakeSeverity::WarningOnly->value)
            ->setParameter('emergency', BrakeSeverity::Emergency->value)
            ->setParameter('endangered', $values(array_filter(Consequence::cases(), static fn (Consequence $c): bool => $c->endangeredFollower())), ArrayParameterType::STRING)
            ->setParameter('collisions', $values(array_filter(Consequence::cases(), static fn (Consequence $c): bool => $c->isCollision())), ArrayParameterType::STRING)
            ->setParameter('fastRoads', $values([RoadType::Motorway, RoadType::Expressway]), ArrayParameterType::STRING)
            ->setParameter('infrastructure', $values([
                RoadSituation::TunnelEntrance, RoadSituation::InsideTunnel, RoadSituation::TunnelExit,
                RoadSituation::UnderViaduct, RoadSituation::Bridge, RoadSituation::SignGantry,
            ]), ArrayParameterType::STRING)
            ->setParameter('firstTime', Recurrence::FirstTime->value)
            ->setParameter('notContacted', DealerResponse::NotContacted->value)
            ->setParameter('dismissive', $values(array_filter(DealerResponse::cases(), static fn (DealerResponse $d): bool => $d->isDismissive())), ArrayParameterType::STRING)
            ->getQuery()
            ->getSingleResult();

        $repeatVehicles = \count($this->base($filter)
            ->select('i.plateFingerprint')
            ->groupBy('i.plateFingerprint')
            ->having('COUNT(i.id) > 1')
            ->getQuery()
            ->getScalarResult());

        return new Totals(
            reports: (int) $row['reports'],
            vehicles: (int) $row['vehicles'],
            repeatVehicles: $repeatVehicles,
            makes: (int) $row['makes'],
            actuallyBraked: (int) $row['actuallyBraked'],
            emergencyStops: (int) $row['emergencyStops'],
            endangeredFollower: (int) $row['endangeredFollower'],
            collisions: (int) $row['collisions'],
            onMotorway: (int) $row['onMotorway'],
            atInfrastructure: (int) $row['atInfrastructure'],
            recurring: (int) $row['recurring'],
            dealerContacted: (int) $row['dealerContacted'],
            dealerDismissive: (int) $row['dealerDismissive'],
            reportedToAuthority: (int) $row['reportedToAuthority'],
            withFootage: (int) $row['withFootage'],
            averageSpeedBefore: null === $row['averageSpeedBefore'] ? null : round((float) $row['averageSpeedBefore'], 1),
            averageSpeedDrop: null === $row['averageSpeedDrop'] ? null : round((float) $row['averageSpeedDrop'], 1),
        );
    }

    /** @return array<int|string, int> numeric keys become ints (PHP array semantics) */
    private function groupCount(IncidentFilter $filter, string $field, ?int $limit = null, bool $excludeNull = false): array
    {
        $qb = $this->base($filter)
            ->select($field.' AS k', 'COUNT(i.id) AS c')
            ->groupBy($field)
            ->orderBy('c', 'DESC');

        if ($excludeNull) {
            $qb->andWhere($field.' IS NOT NULL');
        }
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        $result = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $key = $row['k'] instanceof \BackedEnum ? (string) $row['k']->value : (string) $row['k'];
            $result[$key] = (int) $row['c'];
        }

        return $result;
    }

    /** @return array<string, int> */
    private function byMonth(IncidentFilter $filter, \DateTimeImmutable $now): array
    {
        // The selected period, or the last 12 months; never more than 60 bars.
        $last = ($filter->to ?? $now)->modify('first day of this month')->setTime(0, 0);
        $first = ($filter->from ?? $last->modify('-11 months'))->modify('first day of this month')->setTime(0, 0);
        if ($first < $last->modify('-59 months')) {
            $first = $last->modify('-59 months');
        }

        $months = [];
        for ($cursor = $first; $cursor <= $last; $cursor = $cursor->modify('+1 month')) {
            $months[$cursor->format('Y-m')] = 0;
        }

        $counts = $this->groupCount($filter, 'i.occurredMonth');
        foreach ($months as $month => $_) {
            $months[$month] = $counts[$month] ?? 0;
        }

        return $months;
    }

    /** @return array<int, int> */
    private function byHour(IncidentFilter $filter): array
    {
        $counts = $this->groupCount($filter, 'i.occurredHour');
        $hours = [];
        for ($h = 0; $h < 24; ++$h) {
            $hours[$h] = $counts[$h] ?? 0;
        }

        return $hours;
    }

    /** @return list<array{make: string, model: string, reports: int, vehicles: int}> */
    private function topModels(IncidentFilter $filter): array
    {
        $rows = $this->base($filter)
            ->select('i.vehicle.make AS make', 'i.vehicle.model AS model', 'COUNT(i.id) AS reports', 'COUNT(DISTINCT i.plateFingerprint) AS vehicles')
            ->groupBy('i.vehicle.make, i.vehicle.model')
            ->orderBy('reports', 'DESC')
            ->addOrderBy('vehicles', 'DESC')
            ->setMaxResults(15)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): array => [
            'make' => (string) $r['make'],
            'model' => (string) $r['model'],
            'reports' => (int) $r['reports'],
            'vehicles' => (int) $r['vehicles'],
        ], $rows));
    }

    /** @return list<array{country: string, road: string, reports: int}> */
    private function topRoads(IncidentFilter $filter): array
    {
        $rows = $this->base($filter)
            ->select('i.location.country AS country', 'i.location.roadNumber AS road', 'COUNT(i.id) AS reports')
            ->andWhere('i.location.roadNumber IS NOT NULL')
            ->groupBy('i.location.country, i.location.roadNumber')
            ->orderBy('reports', 'DESC')
            ->addOrderBy('road', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): array => [
            'country' => (string) $r['country'],
            'road' => (string) $r['road'],
            'reports' => (int) $r['reports'],
        ], $rows));
    }

    /** @return array<int, int> */
    private function byFirstAdmissionYear(IncidentFilter $filter): array
    {
        $rows = $this->base($filter)
            ->select('i.vehicle.firstAdmission AS d', 'COUNT(i.id) AS c')
            ->andWhere('i.vehicle.firstAdmission IS NOT NULL')
            ->groupBy('i.vehicle.firstAdmission')
            ->getQuery()
            ->getArrayResult();

        $years = [];
        foreach ($rows as $row) {
            $year = (int) ($row['d'] instanceof \DateTimeInterface ? $row['d']->format('Y') : substr((string) $row['d'], 0, 4));
            $years[$year] = ($years[$year] ?? 0) + (int) $row['c'];
        }
        ksort($years);

        return $years;
    }

    /** @return array<string, int> */
    private function bySpeed(IncidentFilter $filter): array
    {
        $buckets = [];
        $bounds = self::SPEED_BUCKETS;
        for ($i = 0; $i < \count($bounds) - 1; ++$i) {
            $buckets[$this->speedLabel($bounds[$i], $bounds[$i + 1])] = 0;
        }

        foreach ($this->groupCount($filter, 'i.speedBefore') as $speed => $count) {
            $speed = (int) $speed;
            for ($i = \count($bounds) - 2; $i >= 0; --$i) {
                if ($speed >= $bounds[$i]) {
                    $buckets[$this->speedLabel($bounds[$i], $bounds[$i + 1])] += $count;
                    break;
                }
            }
        }

        return $buckets;
    }

    private function speedLabel(int $from, int $to): string
    {
        return 131 === $to ? '≥ '.$from : sprintf('%d–%d', $from, $to - 1);
    }

    private function base(IncidentFilter $filter): QueryBuilder
    {
        return IncidentFilterApplier::apply($this->entityManager->createQueryBuilder()->from(Incident::class, 'i'), $filter);
    }
}
