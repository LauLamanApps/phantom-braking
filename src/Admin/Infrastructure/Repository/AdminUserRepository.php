<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Repository;

use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\Repository\AdminUserRepositoryInterface;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Infrastructure\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<AdminUser>
 */
final class AdminUserRepository extends AbstractRepository implements AdminUserRepositoryInterface
{
    protected function getEntity(): string
    {
        return AdminUser::class;
    }

    public function get(AdminUserId $id): AdminUser
    {
        return $this->find($id);
    }

    public function getByName(string $name): AdminUser
    {
        return $this->findOneBy(['name' => AdminUser::normaliseName($name)]);
    }

    public function existsWithName(string $name): bool
    {
        return null !== $this->repository()->findOneBy(['name' => AdminUser::normaliseName($name)]);
    }

    public function persist(AdminUser $adminUser): void
    {
        $this->entityManager->persist($adminUser);
    }
}
