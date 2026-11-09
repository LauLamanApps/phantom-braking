<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

final readonly class DenyMediaDeletionCommand implements Command
{
    public function __construct(public IncidentId $incidentId)
    {
    }
}
