<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

/** A file the reporter uploaded, still sitting in a temporary location. */
final readonly class FootageUpload
{
    public function __construct(
        public string $temporaryPath,
        public string $extension,
        public string $mimeType,
        public int $size,
        public bool $publicationConsent,
    ) {
    }
}
