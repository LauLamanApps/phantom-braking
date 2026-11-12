<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

/** Right after reporting, the reporter says they also reported it to the ILT. */
final readonly class ConfirmReportedToAuthorityCommand implements Command
{
    public function __construct(public IncidentId $incidentId)
    {
    }
}
