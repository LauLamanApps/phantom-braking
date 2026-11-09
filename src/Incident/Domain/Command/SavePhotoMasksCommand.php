<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use App\SharedKernel\Domain\Messenger\Transport\Command;

/** A moderator sets the plate masks of a photo; the public copy is regenerated from the private original. */
final readonly class SavePhotoMasksCommand implements Command
{
    /** @param list<PlateMask> $masks */
    public function __construct(
        public PhotoId $photoId,
        public array $masks,
    ) {
    }
}
