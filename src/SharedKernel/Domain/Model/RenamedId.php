<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * An ID that was replaced by another, so links with the old one keep working. Written once, by the migration that
 * moved the existing reports, photos and admins from UUIDv4 to v7; IDs are unique across entities.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'renamed_id')]
class RenamedId
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $oldId,
        #[ORM\Column(type: 'guid')]
        private string $newId,
    ) {
    }

    public function getOldId(): string
    {
        return $this->oldId;
    }

    public function getNewId(): string
    {
        return $this->newId;
    }
}
