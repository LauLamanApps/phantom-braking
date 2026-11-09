<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Repository;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Repository\NoResultException;
use App\SharedKernel\Infrastructure\Repository\AbstractRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends AbstractRepository<Incident>
 */
final class IncidentRepository extends AbstractRepository implements IncidentRepositoryInterface
{
    protected function getEntity(): string
    {
        return Incident::class;
    }

    public function get(IncidentId $id): Incident
    {
        return $this->find($id);
    }

    public function getPage(IncidentFilter $filter, int $page, int $perPage): array
    {
        /* @var list<Incident> */
        return $this->filtered($filter)
            ->orderBy('i.occurredAt', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function count(IncidentFilter $filter): int
    {
        return (int) $this->filtered($filter)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByCountry(IncidentFilter $filter): array
    {
        $counts = [];
        foreach ($this->countBy($this->filtered($filter->withoutCountry()), 'i.location.country') as $country => $count) {
            $counts[(string) $country] = $count;
        }

        return $counts;
    }

    public function countByMake(IncidentFilter $filter): array
    {
        return $this->countBy($this->filtered($filter->withoutVehicle()), 'i.vehicle.make');
    }

    public function countByModel(IncidentFilter $filter): array
    {
        if (null === $filter->make) {
            return [];
        }

        return $this->countBy($this->filtered($filter->withoutModel()), 'i.vehicle.model');
    }

    public function getByPhoto(PhotoId $photoId): Incident
    {
        $incident = $this->repository()->createQueryBuilder('i')
            ->innerJoin('i.photos', 'p')
            ->where('p.id = :photo')
            ->setParameter('photo', (string) $photoId)
            ->getQuery()
            ->getOneOrNullResult();

        return $incident ?? throw new NoResultException(sprintf('No report with photo "%s".', $photoId));
    }

    public function getWithPendingMedia(): array
    {
        return $this->repository()->createQueryBuilder('i')
            ->leftJoin('i.photos', 'p')
            ->distinct()
            ->where('i.footage.status = :pending OR p.status = :pending')
            ->setParameter('pending', ModerationStatus::Pending)
            ->orderBy('i.reportedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getWithDeletionRequests(): array
    {
        return $this->repository()->createQueryBuilder('i')
            ->where('i.mediaDeletionRequestedAt IS NOT NULL')
            ->orderBy('i.mediaDeletionRequestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function persist(Incident $incident): void
    {
        $this->entityManager->persist($incident);
    }

    /** @return array<int|string, int> */
    private function countBy(QueryBuilder $qb, string $field): array
    {
        $rows = $qb->select($field.' AS k', 'COUNT(i.id) AS c')
            ->groupBy($field)
            ->orderBy('c', 'DESC')
            ->addOrderBy('k', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['k'] instanceof \BackedEnum ? $row['k']->value : $row['k'];
            $result[(string) $key] = (int) $row['c'];
        }

        return $result;
    }

    private function filtered(IncidentFilter $filter): QueryBuilder
    {
        return IncidentFilterApplier::apply($this->repository()->createQueryBuilder('i'), $filter);
    }
}
