<?php

declare(strict_types=1);

namespace App\Incident\Domain\Repository;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Repository\NoResultException;

interface IncidentRepositoryInterface
{
    /** @throws NoResultException */
    public function get(IncidentId $id): Incident;

    /** @return list<Incident> newest first */
    public function getPage(IncidentFilter $filter, int $page, int $perPage): array;

    public function count(IncidentFilter $filter): int;

    /** @return array<string, int> incident country code → reports (ignores the filter's own country), most reported first */
    public function countByCountry(IncidentFilter $filter): array;

    /** @return array<int|string, int> make → reports, most reported first (numeric names become int keys) */
    public function countByMake(IncidentFilter $filter): array;

    /** @return array<int|string, int> model → reports for the filter's make, most reported first (e.g. PEUGEOT 2008) */
    public function countByModel(IncidentFilter $filter): array;

    /** @throws NoResultException */
    public function getByPhoto(PhotoId $photoId): Incident;

    /** @return list<Incident> incidents with footage or photos awaiting moderation, oldest first */
    public function getWithPendingMedia(): array;

    /** @return list<Incident> incidents whose reporter asked to delete the media, oldest request first */
    public function getWithDeletionRequests(): array;

    /** @return list<IncidentId> reports with a location whose sun or measured weather is still missing, newest first */
    public function getIdsNeedingMeasuredConditions(int $limit): array;

    public function persist(Incident $incident): void;

    public function flush(): void;
}
