<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Repository;

use App\Admin\Domain\Model\AdminPasskey;
use App\Admin\Domain\Repository\AdminPasskeyRepositoryInterface;
use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Infrastructure\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<AdminPasskey>
 */
final class AdminPasskeyRepository extends AbstractRepository implements AdminPasskeyRepositoryInterface
{
    protected function getEntity(): string
    {
        return AdminPasskey::class;
    }

    public function get(AdminPasskeyId $id): AdminPasskey
    {
        return $this->find($id);
    }

    public function getByCredentialId(string $credentialId): AdminPasskey
    {
        return $this->findOneBy(['publicKeyCredentialId' => $credentialId]);
    }

    public function getAllForAdmin(AdminUserId $adminUserId): array
    {
        return $this->repository()->findBy(['adminUserId' => $adminUserId], ['createdAt' => 'DESC']);
    }

    public function countForAdmin(AdminUserId $adminUserId): int
    {
        return $this->repository()->count(['adminUserId' => $adminUserId]);
    }

    public function persist(AdminPasskey $passkey): void
    {
        $this->entityManager->persist($passkey);
    }

    public function remove(AdminPasskey $passkey): void
    {
        $this->entityManager->remove($passkey);
    }
}
