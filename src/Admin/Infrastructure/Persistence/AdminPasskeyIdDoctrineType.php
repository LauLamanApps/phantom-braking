<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Persistence;

use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\SharedKernel\Infrastructure\Persistence\AbstractIdDoctrineType;

final class AdminPasskeyIdDoctrineType extends AbstractIdDoctrineType
{
    public function getClassName(): string
    {
        return AdminPasskeyId::class;
    }

    public function getName(): string
    {
        return 'admin_passkey_id';
    }
}
