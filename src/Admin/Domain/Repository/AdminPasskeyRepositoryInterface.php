<?php

declare(strict_types=1);

namespace App\Admin\Domain\Repository;

use App\Admin\Domain\Model\AdminPasskey;
use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Repository\NoResultException;

interface AdminPasskeyRepositoryInterface
{
    /** @throws NoResultException */
    public function get(AdminPasskeyId $id): AdminPasskey;

    /** @throws NoResultException */
    public function getByCredentialId(string $credentialId): AdminPasskey;

    /** @return list<AdminPasskey> newest first */
    public function getAllForAdmin(AdminUserId $adminUserId): array;

    public function countForAdmin(AdminUserId $adminUserId): int;

    public function persist(AdminPasskey $passkey): void;

    public function remove(AdminPasskey $passkey): void;

    public function flush(): void;
}
