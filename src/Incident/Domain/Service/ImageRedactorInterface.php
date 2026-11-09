<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\ValueObject\PlateMask;

/** Burns plate masks into a copy of an image; the source is left untouched. */
interface ImageRedactorInterface
{
    /**
     * @param list<PlateMask> $masks
     *
     * @return string path of a temporary JPEG with the masks applied
     */
    public function redact(string $sourcePath, array $masks): string;
}
