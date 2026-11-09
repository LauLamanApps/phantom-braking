<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Persistence;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Infrastructure\Persistence\AbstractIdDoctrineType;

final class IncidentIdDoctrineType extends AbstractIdDoctrineType
{
    public function getClassName(): string
    {
        return IncidentId::class;
    }

    public function getName(): string
    {
        return 'incident_id';
    }
}
