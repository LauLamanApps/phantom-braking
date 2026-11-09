<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;

/**
 * (Re)creates the public copy of a photo: the private original with the plate
 * masks burnt in. Always regenerated from the original, so removing a mask works too.
 */
final readonly class PhotoPublisher
{
    public function __construct(
        private MediaStorageInterface $mediaStorage,
        private ImageRedactorInterface $redactor,
    ) {
    }

    /** @param list<PlateMask> $masks */
    public function publishMasked(Incident $incident, PhotoId $photoId, array $masks): void
    {
        $photo = $incident->getPhoto($photoId);
        $redacted = $this->mediaStorage->withLocalCopy($photo->getPath(), fn (string $original): string => $this->redactor->redact($original, $masks));
        $publishedPath = $this->mediaStorage->store($incident->getId(), sprintf('photo-%s-public.jpg', $photoId), $redacted, 'image/jpeg');

        $incident->maskPhoto($photoId, $masks, $publishedPath);
    }
}
