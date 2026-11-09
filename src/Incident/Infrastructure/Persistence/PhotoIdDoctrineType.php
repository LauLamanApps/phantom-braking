<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Persistence;

use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Infrastructure\Persistence\AbstractIdDoctrineType;

final class PhotoIdDoctrineType extends AbstractIdDoctrineType
{
    public function getClassName(): string
    {
        return PhotoId::class;
    }

    public function getName(): string
    {
        return 'photo_id';
    }
}
