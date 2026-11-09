<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use Doctrine\ORM\Mapping as ORM;

/**
 * A photo of the aftermath (damage, the scene). Like footage it may show
 * other people, so it stays private until moderated and is only published
 * with the reporter's consent. Stored re-encoded, without EXIF/GPS metadata.
 */
#[ORM\Entity]
#[ORM\Table(name: 'incident_photo')]
class Photo
{
    #[ORM\Column(enumType: ModerationStatus::class)]
    private ModerationStatus $status = ModerationStatus::Pending;

    /** The copy with plates masked: the only version that is ever published. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publishedPath = null;

    /** @var list<array{float, float, float, float}> */
    #[ORM\Column(type: 'json')]
    private array $masks = [];

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'photo_id')]
        private PhotoId $id,
        #[ORM\ManyToOne(targetEntity: Incident::class, inversedBy: 'photos')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Incident $incident,
        #[ORM\Column]
        private int $position,
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column(length: 50)]
        private string $mimeType,
        #[ORM\Column]
        private int $size,
        #[ORM\Column]
        private int $width,
        #[ORM\Column]
        private int $height,
        #[ORM\Column]
        private bool $publicationConsent,
    ) {
    }

    public function approve(): void
    {
        if (ModerationStatus::Withdrawn === $this->status) {
            throw new \DomainException('The reporter withdrew consent for this photo.');
        }
        if (!$this->publicationConsent) {
            throw new \DomainException('The reporter did not consent to publication.');
        }
        if (null === $this->publishedPath) {
            throw new \DomainException('This photo has no masked copy yet.');
        }

        $this->status = ModerationStatus::Approved;
    }

    public function reject(): void
    {
        if (ModerationStatus::Withdrawn !== $this->status) {
            $this->status = ModerationStatus::Rejected;
        }
    }

    public function isPublic(): bool
    {
        return ModerationStatus::Approved === $this->status && null !== $this->publishedPath;
    }

    /** @param list<PlateMask> $masks */
    public function mask(array $masks, string $publishedPath): void
    {
        $this->masks = array_map(static fn (PlateMask $m): array => $m->toArray(), $masks);
        $this->publishedPath = $publishedPath;
    }

    /** @return list<PlateMask> */
    public function getMasks(): array
    {
        return PlateMask::listFromArray($this->masks);
    }

    public function getPublishedPath(): ?string
    {
        return $this->publishedPath;
    }

    public function getId(): PhotoId
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function hasPublicationConsent(): bool
    {
        return $this->publicationConsent;
    }

    public function getStatus(): ModerationStatus
    {
        return $this->status;
    }
}
