<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

/** A stored file (or part of it, for range requests), ready to stream. */
final readonly class MediaObject
{
    /**
     * @param iterable<string>     $chunks the body, in chunks
     * @param array{int, int}|null $range  first and last byte served, or null for the whole file
     */
    public function __construct(
        public iterable $chunks,
        public int $contentLength,
        public int $totalSize,
        public ?array $range = null,
    ) {
    }
}
