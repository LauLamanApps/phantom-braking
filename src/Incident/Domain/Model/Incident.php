<?php

declare(strict_types=1);

namespace App\Incident\Domain\Model;

use App\Incident\Domain\Exception\AssistSystemNotAvailableException;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Authority;
use App\Vehicle\Domain\ValueObject\Country;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One phantom-braking report: the car braked (or warned) without a real
 * obstacle. Everything a statistic is built on lives here; the plate
 * itself is deliberately not stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'incident')]
#[ORM\Index(name: 'idx_incident_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_incident_make', columns: ['vehicle_make'])]
#[ORM\Index(name: 'idx_incident_plate', columns: ['plate_fingerprint'])]
class Incident
{
    public const int MAX_PHOTOS = 6;

    #[ORM\Id]
    #[ORM\Column(type: 'incident_id')]
    private IncidentId $id;

    /** Denormalised for portable GROUP BY (no DB-specific date functions). */
    #[ORM\Column(length: 7)]
    private string $occurredMonth;

    #[ORM\Column]
    private int $occurredHour;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $reportedAt;

    #[ORM\Embedded(class: DashcamFootage::class, columnPrefix: 'footage_')]
    private DashcamFootage $footage;

    /** Set when the reporter asked (and proved with the plate) to delete all media; an admin decides. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $mediaDeletionRequestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $mediaDeletedAt = null;

    /** Set when the reporter confirmed on the prompt after reporting that they also reported it to the ILT: shows whether that prompt works. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reportedToAuthorityViaPromptAt = null;

    #[ORM\Embedded(class: SunPosition::class, columnPrefix: 'sun_')]
    private SunPosition $sun;

    #[ORM\Embedded(class: MeasuredWeather::class, columnPrefix: 'measured_')]
    private MeasuredWeather $measuredWeather;

    /** @var Collection<int, Photo> */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'incident', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $photos;

    public function __construct(
        IncidentId $id,
        #[ORM\Column(length: 2, enumType: Country::class)]
        private Country $country,
        #[ORM\Column(length: 64)]
        private string $plateFingerprint,
        #[ORM\Embedded(class: VehicleSnapshot::class, columnPrefix: 'vehicle_')]
        private VehicleSnapshot $vehicle,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Embedded(class: Location::class, columnPrefix: 'location_')]
        private Location $location,
        #[ORM\Column(enumType: BrakeSeverity::class)]
        private BrakeSeverity $severity,
        #[ORM\Column]
        private int $speedBefore,
        #[ORM\Column(nullable: true)]
        private ?int $speedAfter,
        #[ORM\Column(enumType: SuspectedTrigger::class)]
        private SuspectedTrigger $trigger,
        #[ORM\Column(enumType: ActiveAssistSystem::class)]
        private ActiveAssistSystem $assistSystem,
        #[ORM\Column(nullable: true)]
        private ?bool $warningShown,
        #[ORM\Column(enumType: Weather::class)]
        private Weather $weather,
        #[ORM\Column(enumType: LightCondition::class)]
        private LightCondition $light,
        #[ORM\Column(enumType: Consequence::class)]
        private Consequence $consequence,
        #[ORM\Column(enumType: Recurrence::class)]
        private Recurrence $recurrence,
        #[ORM\Column(enumType: DealerResponse::class)]
        private DealerResponse $dealerResponse,
        #[ORM\Column]
        private bool $reportedToAuthority,
        #[ORM\Column(nullable: true)]
        private ?bool $windscreenReplacedRecently,
        #[ORM\Column(nullable: true)]
        private ?int $odometerKm,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $softwareVersion,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description,
        \DateTimeImmutable $reportedAt,
    ) {
        if ($occurredAt > $reportedAt) {
            throw new \DomainException('An incident cannot happen after it was reported.');
        }
        if ($speedBefore < 0 || $speedBefore > 300) {
            throw new \DomainException('Speed before braking is out of range.');
        }
        if (null !== $speedAfter && ($speedAfter < 0 || $speedAfter > $speedBefore)) {
            throw new \DomainException('Speed after braking must be between 0 and the speed before.');
        }
        if (!$assistSystem->isAvailableFor($vehicle->getMake())) {
            throw new AssistSystemNotAvailableException(sprintf('"%s" is not available on a %s.', $assistSystem->value, $vehicle->getMake()));
        }

        $this->id = $id;
        $this->reportedAt = $reportedAt;
        $this->occurredMonth = $occurredAt->format('Y-m');
        $this->occurredHour = (int) $occurredAt->format('G');
        $this->description = null === $description || '' === trim($description) ? null : trim($description);
        $this->softwareVersion = null === $softwareVersion || '' === trim($softwareVersion) ? null : trim($softwareVersion);
        $this->footage = DashcamFootage::none();
        $this->photos = new ArrayCollection();
        $this->sun = SunPosition::unknown();
        $this->measuredWeather = MeasuredWeather::notChecked();
        $this->calculateSunPosition();
    }

    /**
     * The moment it happened. The reporter gives the local time where it happened, which is stored as is
     * (the hour chart counts local hours); for the sun and the weather it has to be a real instant.
     */
    public function getOccurredInstant(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->occurredAt->format('Y-m-d H:i:s'), $this->location->getCountry()->timezone());
    }

    /** Idempotent; also used to fill in reports from before the sun was calculated. */
    public function calculateSunPosition(): void
    {
        if (!$this->location->hasCoordinates()) {
            return;
        }

        $this->sun = SunPosition::at((float) $this->location->getLatitude(), (float) $this->location->getLongitude(), $this->getOccurredInstant());
    }

    public function getSun(): SunPosition
    {
        return $this->sun;
    }

    /** Only reports with a location can be looked up, and each only once. */
    public function needsMeasuredWeather(): bool
    {
        return $this->location->hasCoordinates() && !$this->measuredWeather->isChecked();
    }

    public function recordMeasuredWeather(MeasuredWeather $weather): void
    {
        if (!$weather->isChecked()) {
            throw new \DomainException('Only a completed weather lookup can be recorded.');
        }

        $this->measuredWeather = $weather;
    }

    public function getMeasuredWeather(): MeasuredWeather
    {
        return $this->measuredWeather;
    }

    public function attachFootage(DashcamFootage $footage): void
    {
        if ($this->footage->exists()) {
            throw new \DomainException('This report already has footage.');
        }

        $this->footage = $footage;
    }

    public function approveFootage(): void
    {
        $this->footage->approve();
    }

    public function rejectFootage(): void
    {
        $this->footage->reject();
    }

    public function addPhoto(PhotoId $id, string $path, string $mimeType, int $size, int $width, int $height, bool $publicationConsent): Photo
    {
        if ($this->photos->count() >= self::MAX_PHOTOS) {
            throw new \DomainException(sprintf('A report can have at most %d photos.', self::MAX_PHOTOS));
        }

        $photo = new Photo($id, $this, $this->photos->count(), $path, $mimeType, $size, $width, $height, $publicationConsent);
        $this->photos->add($photo);

        return $photo;
    }

    public function hasMedia(): bool
    {
        return $this->footage->exists() || !$this->photos->isEmpty();
    }

    /** The reporter (proven by the plate) asks to delete all footage and photos. An admin decides. */
    public function requestMediaDeletion(\DateTimeImmutable $now): void
    {
        if (!$this->hasMedia()) {
            throw new \DomainException('This report has no footage or photos.');
        }

        $this->mediaDeletionRequestedAt ??= $now;
    }

    public function denyMediaDeletion(): void
    {
        $this->mediaDeletionRequestedAt = null;
    }

    /**
     * Removes all footage and photos from the report.
     *
     * @return list<string> storage keys of the files to delete (after the change is persisted)
     */
    public function deleteAllMedia(\DateTimeImmutable $now): array
    {
        $keys = [];
        if (null !== $this->footage->getPath()) {
            $keys[] = $this->footage->getPath();
        }
        foreach ($this->photos as $photo) {
            $keys[] = $photo->getPath();
            if (null !== $photo->getPublishedPath()) {
                $keys[] = $photo->getPublishedPath();
            }
        }

        $this->footage = DashcamFootage::none();
        $this->photos->clear();
        $this->mediaDeletionRequestedAt = null;
        $this->mediaDeletedAt = $now;

        return array_values(array_unique($keys));
    }

    public function getMediaDeletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->mediaDeletionRequestedAt;
    }

    public function getMediaDeletedAt(): ?\DateTimeImmutable
    {
        return $this->mediaDeletedAt;
    }

    /** @param list<PlateMask> $masks */
    public function maskPhoto(PhotoId $id, array $masks, string $publishedPath): void
    {
        $this->photo($id)->mask($masks, $publishedPath);
    }

    public function getPhoto(PhotoId $id): Photo
    {
        return $this->photo($id);
    }

    public function approvePhoto(PhotoId $id): void
    {
        $this->photo($id)->approve();
    }

    public function rejectPhoto(PhotoId $id): void
    {
        $this->photo($id)->reject();
    }

    /** @return list<Photo> */
    public function getPhotos(): array
    {
        return array_values($this->photos->toArray());
    }

    /** @return list<Photo> */
    public function getPublicPhotos(): array
    {
        return array_values(array_filter($this->getPhotos(), static fn (Photo $p): bool => $p->isPublic()));
    }

    private function photo(PhotoId $id): Photo
    {
        foreach ($this->photos as $photo) {
            if ($photo->getId()->equals($id)) {
                return $photo;
            }
        }

        throw new \DomainException('This photo does not belong to the report.');
    }

    /** km/h lost during the event; null when the reporter did not know. */
    public function getSpeedDrop(): ?int
    {
        return null === $this->speedAfter ? null : $this->speedBefore - $this->speedAfter;
    }

    public function getId(): IncidentId
    {
        return $this->id;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getPlateFingerprint(): string
    {
        return $this->plateFingerprint;
    }

    public function getVehicle(): VehicleSnapshot
    {
        return $this->vehicle;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getSeverity(): BrakeSeverity
    {
        return $this->severity;
    }

    public function getSpeedBefore(): int
    {
        return $this->speedBefore;
    }

    public function getSpeedAfter(): ?int
    {
        return $this->speedAfter;
    }

    public function getTrigger(): SuspectedTrigger
    {
        return $this->trigger;
    }

    public function getAssistSystem(): ActiveAssistSystem
    {
        return $this->assistSystem;
    }

    public function wasWarningShown(): ?bool
    {
        return $this->warningShown;
    }

    public function getWeather(): Weather
    {
        return $this->weather;
    }

    public function getLight(): LightCondition
    {
        return $this->light;
    }

    public function getConsequence(): Consequence
    {
        return $this->consequence;
    }

    public function getRecurrence(): Recurrence
    {
        return $this->recurrence;
    }

    public function getDealerResponse(): DealerResponse
    {
        return $this->dealerResponse;
    }

    public function isReportedToAuthority(): bool
    {
        return $this->reportedToAuthority;
    }

    /** The authority for the country where the car is registered, if there is one we guide reporters to. */
    public function getAuthority(): ?Authority
    {
        return $this->country->authority();
    }

    /** AEB is mandatory on this car, so its country's authority should know about it, and the reporter hasn't told them yet. */
    public function shouldBeReportedToAuthority(): bool
    {
        return null !== $this->getAuthority() && !$this->reportedToAuthority && true === $this->vehicle->isSubjectToGsr2();
    }

    /** The reporter confirms, on the prompt after reporting, that they reported it to the authority as well. */
    public function confirmReportedToAuthorityViaPrompt(\DateTimeImmutable $now): void
    {
        if ($this->reportedToAuthority) {
            return;
        }

        $this->reportedToAuthority = true;
        $this->reportedToAuthorityViaPromptAt = $now;
    }

    public function getReportedToAuthorityViaPromptAt(): ?\DateTimeImmutable
    {
        return $this->reportedToAuthorityViaPromptAt;
    }

    public function wasWindscreenReplacedRecently(): ?bool
    {
        return $this->windscreenReplacedRecently;
    }

    public function getOdometerKm(): ?int
    {
        return $this->odometerKm;
    }

    public function getSoftwareVersion(): ?string
    {
        return $this->softwareVersion;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getReportedAt(): \DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function getFootage(): DashcamFootage
    {
        return $this->footage;
    }
}
