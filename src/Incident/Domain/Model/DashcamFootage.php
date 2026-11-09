<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\ValueObject\ModerationStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Dashcam footage contains other road users (faces, plates). It stays
 * private until a moderator has checked it, and is only ever published when
 * the reporter consented to that.
 */
#[ORM\Embeddable]
class DashcamFootage
{
    #[ORM\Column(enumType: ModerationStatus::class, nullable: true)]
    private ?ModerationStatus $status;

    public function __construct(
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $path,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $mimeType,
        #[ORM\Column(nullable: true)]
        private ?int $size,
        #[ORM\Column(nullable: true)]
        private ?bool $publicationConsent,
    ) {
        $this->status = null === $path ? null : ModerationStatus::Pending;
    }

    public static function none(): self
    {
        return new self(null, null, null, null);
    }

    public function exists(): bool
    {
        return null !== $this->path;
    }

    public function approve(): void
    {
        if (!$this->exists()) {
            throw new \DomainException('There is no footage to approve.');
        }
        if (ModerationStatus::Withdrawn === $this->status) {
            throw new \DomainException('The reporter withdrew consent for this footage.');
        }
        if (true !== $this->publicationConsent) {
            throw new \DomainException('The reporter did not consent to publication.');
        }

        $this->status = ModerationStatus::Approved;
    }

    public function reject(): void
    {
        if (!$this->exists()) {
            throw new \DomainException('There is no footage to reject.');
        }
        if (ModerationStatus::Withdrawn === $this->status) {
            return; // already offline for good
        }

        $this->status = ModerationStatus::Rejected;
    }

    public function isPublic(): bool
    {
        return ModerationStatus::Approved === $this->status;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function hasPublicationConsent(): bool
    {
        return true === $this->publicationConsent;
    }

    public function getStatus(): ?ModerationStatus
    {
        return $this->status;
    }
}
