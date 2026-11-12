<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Repository;

use App\SharedKernel\Domain\Model\RenamedId;

interface RenamedIdRepositoryInterface
{
    /** @throws NoResultException */
    public function getByOldId(string $oldId): RenamedId;
}
