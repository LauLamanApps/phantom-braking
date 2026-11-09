<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Repository;

use App\Incident\Domain\ValueObject\IncidentFilter;
use Doctrine\ORM\QueryBuilder;

/**
 * Translates an IncidentFilter into DQL conditions on an Incident alias.
 * Shared by the repository and the Statistics read model, which queries the
 * same table, so a filtered list and its statistics always agree.
 */
final class IncidentFilterApplier
{
    public static function apply(QueryBuilder $qb, IncidentFilter $filter, string $alias = 'i'): QueryBuilder
    {
        if (null !== $filter->country) {
            $qb->andWhere($alias.'.location.country = :f_country')->setParameter('f_country', $filter->country->code);
        }
        if (null !== $filter->make) {
            $qb->andWhere($alias.'.vehicle.make = :f_make')->setParameter('f_make', $filter->make);
        }
        if (null !== $filter->model) {
            $qb->andWhere($alias.'.vehicle.model = :f_model')->setParameter('f_model', $filter->model);
        }
        if (null !== $filter->roadType) {
            $qb->andWhere($alias.'.location.roadType = :f_road_type')->setParameter('f_road_type', $filter->roadType->value);
        }
        if (null !== $filter->from) {
            $qb->andWhere($alias.'.occurredAt >= :f_from')->setParameter('f_from', $filter->from->setTime(0, 0), 'datetime_immutable');
        }
        if (null !== $filter->to) {
            $qb->andWhere($alias.'.occurredAt < :f_to')->setParameter('f_to', $filter->to->setTime(0, 0)->modify('+1 day'), 'datetime_immutable');
        }

        return $qb;
    }
}
