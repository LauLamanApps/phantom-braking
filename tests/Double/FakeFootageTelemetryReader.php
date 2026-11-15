<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Service\FootageTelemetryReaderInterface;
use App\Incident\Domain\ValueObject\FootageTelemetry;

/** Driving data per local file path; any other file has none. */
final class FakeFootageTelemetryReader implements FootageTelemetryReaderInterface
{
    /** @var list<string> */
    public array $reads = [];

    /** @param array<string, FootageTelemetry> $telemetry */
    public function __construct(private readonly array $telemetry = [])
    {
    }

    public function read(string $path): ?FootageTelemetry
    {
        $this->reads[] = $path;

        return $this->telemetry[$path] ?? null;
    }
}
