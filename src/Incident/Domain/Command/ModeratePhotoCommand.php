<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

final readonly class ModeratePhotoCommand implements Command
{
    public function __construct(
        public PhotoId $photoId,
        public bool $approve,
    ) {
    }
}
