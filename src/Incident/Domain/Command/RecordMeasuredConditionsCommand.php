<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

/** Calculate the sun and look up the measured weather for a report with a location. */
final readonly class RecordMeasuredConditionsCommand implements Command
{
    public function __construct(public IncidentId $incidentId)
    {
    }
}
