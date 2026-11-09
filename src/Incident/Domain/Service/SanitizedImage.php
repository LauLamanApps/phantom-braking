<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

/** A re-encoded image without metadata, in a temporary file. */
final readonly class SanitizedImage
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public int $size,
    ) {
    }
}
