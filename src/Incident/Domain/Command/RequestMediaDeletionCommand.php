<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

/** The reporter asks to delete their footage and photos; the plate proves it's their report. */
final readonly class RequestMediaDeletionCommand implements Command
{
    public function __construct(
        public IncidentId $incidentId,
        #[\SensitiveParameter]
        public string $licensePlate,
    ) {
    }
}
