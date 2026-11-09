<?php

declare(strict_types=1);

namespace App\Admin\Domain\Repository;

use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Repository\NoResultException;

interface AdminUserRepositoryInterface
{
    /** @throws NoResultException */
    public function get(AdminUserId $id): AdminUser;

    /** @throws NoResultException */
    public function getByName(string $name): AdminUser;

    public function existsWithName(string $name): bool;

    public function persist(AdminUser $adminUser): void;

    public function flush(): void;
}
