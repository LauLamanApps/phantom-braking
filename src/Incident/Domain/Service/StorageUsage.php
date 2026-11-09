<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\ValueObject\MediaKind;

/** How much is stored for one kind of media, and where (bucket or directory). */
final readonly class StorageUsage
{
    public function __construct(
        public MediaKind $kind,
        public string $location,
        public int $objects,
        public int $bytes,
    ) {
    }
}
