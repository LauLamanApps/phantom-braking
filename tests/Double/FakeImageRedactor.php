<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Service\ImageRedactorInterface;

/** Records which masks were burnt into which source. */
final class FakeImageRedactor implements ImageRedactorInterface
{
    /** @var list<array{string, int}> source path and mask count per call */
    public array $calls = [];

    public function redact(string $sourcePath, array $masks): string
    {
        $this->calls[] = [$sourcePath, \count($masks)];

        return $sourcePath.'.masked';
    }

    /** @return array{string, int}|null */
    public function lastCall(): ?array
    {
        return [] === $this->calls ? null : $this->calls[\count($this->calls) - 1];
    }
}
