<?php

declare(strict_types=1);

namespace App\Incident\Domain\Event;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\AsyncEvent;

final readonly class IncidentReportedEvent implements AsyncEvent
{
    public function __construct(
        public IncidentId $incidentId,
        public bool $hasFootage,
        public int $photoCount = 0,
    ) {
    }
}
