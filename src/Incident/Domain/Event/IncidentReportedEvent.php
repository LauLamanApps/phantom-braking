<?php

declare(strict_types=1);

namespace App\Incident\Domain\Event;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\Event;

final readonly class IncidentReportedEvent implements Event
{
    public function __construct(
        public IncidentId $incidentId,
        public bool $hasFootage,
        public int $photoCount = 0,
    ) {
    }
}
