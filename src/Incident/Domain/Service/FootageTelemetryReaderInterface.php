<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\ValueObject\FootageTelemetry;

/** Reads the driving data some cars (Tesla, since firmware 2025.44.25) embed in their dashcam videos. */
interface FootageTelemetryReaderInterface
{
    /** Null when the file carries no driving data we can read, or isn't a video we understand. */
    public function read(string $path): ?FootageTelemetry;
}
