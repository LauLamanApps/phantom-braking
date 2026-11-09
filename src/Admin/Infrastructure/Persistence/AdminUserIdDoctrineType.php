<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Persistence;

use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Infrastructure\Persistence\AbstractIdDoctrineType;

final class AdminUserIdDoctrineType extends AbstractIdDoctrineType
{
    public function getClassName(): string
    {
        return AdminUserId::class;
    }

    public function getName(): string
    {
        return 'admin_user_id';
    }
}
