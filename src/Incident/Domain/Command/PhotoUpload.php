<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

/** A photo the reporter uploaded, still in a temporary location and not yet sanitised. */
final readonly class PhotoUpload
{
    public function __construct(
        public string $temporaryPath,
        public bool $publicationConsent,
        /** @var list<\App\Incident\Domain\ValueObject\PlateMask> plates the reporter blacked out */
        public array $masks = [],
    ) {
    }
}
