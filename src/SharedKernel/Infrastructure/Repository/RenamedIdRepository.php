<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Repository;

use App\SharedKernel\Domain\Model\RenamedId;
use App\SharedKernel\Domain\Repository\RenamedIdRepositoryInterface;

/** @extends AbstractRepository<RenamedId> */
final class RenamedIdRepository extends AbstractRepository implements RenamedIdRepositoryInterface
{
    public function getByOldId(string $oldId): RenamedId
    {
        return $this->find(strtolower($oldId));
    }

    protected function getEntity(): string
    {
        return RenamedId::class;
    }
}
