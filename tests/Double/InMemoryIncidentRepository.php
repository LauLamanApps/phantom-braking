<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Repository\NoResultException;

final class InMemoryIncidentRepository implements IncidentRepositoryInterface
{
    /** @var array<string, Incident> */
    private array $pending = [];

    /** @var array<string, Incident> */
    public array $stored = [];

    public int $flushes = 0;

    public function get(IncidentId $id): Incident
    {
        return $this->stored[(string) $id] ?? throw new NoResultException((string) $id);
    }

    public function getPage(IncidentFilter $filter, int $page, int $perPage): array
    {
        $matches = $this->matching($filter);
        usort($matches, static fn (Incident $a, Incident $b): int => $b->getOccurredAt() <=> $a->getOccurredAt());

        return \array_slice($matches, ($page - 1) * $perPage, $perPage);
    }

    public function count(IncidentFilter $filter): int
    {
        return \count($this->matching($filter));
    }

    public function countByCountry(IncidentFilter $filter): array
    {
        $counts = [];
        foreach ($this->countBy($this->matching($filter->withoutCountry()), static fn (Incident $i): string => $i->getLocation()->getCountry()->code) as $country => $count) {
            $counts[(string) $country] = $count;
        }

        return $counts;
    }

    public function countByMake(IncidentFilter $filter): array
    {
        return $this->countBy($this->matching($filter->withoutVehicle()), static fn (Incident $i): string => $i->getVehicle()->getMake());
    }

    public function countByModel(IncidentFilter $filter): array
    {
        return null === $filter->make ? [] : $this->countBy($this->matching($filter->withoutModel()), static fn (Incident $i): string => $i->getVehicle()->getModel());
    }

    public function getByPhoto(PhotoId $photoId): Incident
    {
        foreach ($this->stored as $incident) {
            foreach ($incident->getPhotos() as $photo) {
                if ($photo->getId()->equals($photoId)) {
                    return $incident;
                }
            }
        }

        throw new NoResultException((string) $photoId);
    }

    public function getWithPendingMedia(): array
    {
        return array_values(array_filter($this->stored, static fn (Incident $i): bool => ModerationStatus::Pending === $i->getFootage()->getStatus()
            || [] !== array_filter($i->getPhotos(), static fn ($p): bool => ModerationStatus::Pending === $p->getStatus())));
    }

    public function getWithDeletionRequests(): array
    {
        return array_values(array_filter($this->stored, static fn (Incident $i): bool => null !== $i->getMediaDeletionRequestedAt()));
    }

    public function persist(Incident $incident): void
    {
        $this->pending[(string) $incident->getId()] = $incident;
    }

    public function flush(): void
    {
        $this->stored = $this->pending + $this->stored;
        $this->pending = [];
        ++$this->flushes;
    }

    /** @return list<Incident> */
    private function matching(IncidentFilter $f): array
    {
        return array_values(array_filter($this->stored, static fn (Incident $i): bool => (null === $f->country || $f->country->equals($i->getLocation()->getCountry()))
            && (null === $f->make || $f->make === $i->getVehicle()->getMake())
            && (null === $f->model || $f->model === $i->getVehicle()->getModel())
            && (null === $f->roadType || $f->roadType === $i->getLocation()->getRoadType())
            && (null === $f->from || $i->getOccurredAt() >= $f->from->setTime(0, 0))
            && (null === $f->to || $i->getOccurredAt() < $f->to->setTime(0, 0)->modify('+1 day'))));
    }

    /**
     * @param list<Incident>             $incidents
     * @param callable(Incident): string $key
     *
     * @return array<int|string, int>
     */
    private function countBy(array $incidents, callable $key): array
    {
        $counts = [];
        foreach ($incidents as $incident) {
            $counts[$key($incident)] = ($counts[$key($incident)] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }
}
