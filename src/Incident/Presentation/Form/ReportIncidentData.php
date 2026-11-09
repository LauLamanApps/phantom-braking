<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Vehicle\Domain\ValueObject\Country;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Mutable form model; turned into a ReportIncidentCommand by the controller. */
final class ReportIncidentData
{
    /**
     * About a minute of 4K or three of 1080p dashcam video; the event itself needs 20–60 seconds.
     * Keep in sync with .docker/php/php-ini-overrides.ini and .docker/nginx/nginx.conf.
     */
    public const string MAX_FOOTAGE_SIZE = '300M';
    public const string MAX_PHOTO_SIZE = '15M';

    #[Assert\NotNull]
    public ?Country $country = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Za-z0-9\- ]{1,14}$/', message: 'report.plate.invalid')]
    public ?string $licensePlate = null;

    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('now', message: 'report.occurred_at.future')]
    #[Assert\GreaterThan('-10 years')]
    public ?\DateTimeImmutable $occurredAt = null;

    /** Where it happened; defaults to the registration country but a car can be abroad. */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: IncidentCountry::EUROPE)]
    public ?string $incidentCountry = null;

    #[Assert\NotNull]
    public ?RoadType $roadType = null;

    #[Assert\NotNull]
    public ?RoadSituation $roadSituation = null;

    #[Assert\Length(max: 16)]
    #[Assert\Regex('/^[A-Za-z]{0,2}[\s\-]?\d{1,4}[A-Za-z]?$/', message: 'report.road_number.invalid')]
    public ?string $roadNumber = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    #[Assert\Length(max: 120)]
    public ?string $direction = null;

    #[Assert\Range(min: 15, max: 200)]
    public ?int $speedLimit = null;

    #[Assert\NotNull]
    public ?BrakeSeverity $severity = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 250)]
    public ?int $speedBefore = null;

    #[Assert\Range(min: 0, max: 250)]
    public ?int $speedAfter = null;

    #[Assert\NotNull]
    public ?SuspectedTrigger $trigger = null;

    #[Assert\NotNull]
    public ?ActiveAssistSystem $assistSystem = null;

    public ?bool $warningShown = null;

    #[Assert\NotNull]
    public ?Weather $weather = null;

    #[Assert\NotNull]
    public ?LightCondition $light = null;

    #[Assert\NotNull]
    public ?Consequence $consequence = null;

    #[Assert\NotNull]
    public ?Recurrence $recurrence = null;

    #[Assert\NotNull]
    public ?DealerResponse $dealerResponse = DealerResponse::NotContacted;

    public bool $reportedToAuthority = false;

    public ?bool $windscreenReplacedRecently = null;

    #[Assert\Range(min: 0, max: 2_000_000)]
    public ?int $odometerKm = null;

    #[Assert\Length(max: 64)]
    public ?string $softwareVersion = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\File(
        maxSize: self::MAX_FOOTAGE_SIZE,
        mimeTypes: ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo', 'video/x-matroska', 'video/mpeg', 'video/3gpp'],
        mimeTypesMessage: 'report.footage.type',
    )]
    public ?UploadedFile $footage = null;

    /** @var list<UploadedFile> The maximum count comes from the form's DropzoneType "max_files" option. */
    #[Assert\All([
        new Assert\Image(
            maxSize: self::MAX_PHOTO_SIZE,
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'report.photos.type',
            maxPixels: 40_000_000,
        ),
    ])]
    public array $photos = [];

    /** JSON: per photo (in upload order) a list of plate masks [x, y, w, h] drawn by the reporter. */
    public ?string $photoMasks = null;

    #[Assert\IsTrue(message: 'report.consent.required')]
    public bool $privacyConsent = false;

    #[Assert\Callback]
    public function validateSpeeds(ExecutionContextInterface $context): void
    {
        if (null !== $this->speedAfter && null !== $this->speedBefore && $this->speedAfter > $this->speedBefore) {
            $context->buildViolation('report.speed_after.higher')->atPath('speedAfter')->addViolation();
        }
        try {
            $this->masksPerPhoto();
        } catch (\JsonException|\InvalidArgumentException) {
            $context->buildViolation('report.photos.masks_invalid')->atPath('photos')->addViolation();
        }
        if ((null === $this->latitude) !== (null === $this->longitude)) {
            $context->buildViolation('report.location.incomplete')->atPath('latitude')->addViolation();
        }
    }

    /**
     * @return list<list<PlateMask>> masks per photo, aligned with $photos
     *
     * @throws \JsonException|\InvalidArgumentException
     */
    public function masksPerPhoto(): array
    {
        $decoded = null === $this->photoMasks || '' === $this->photoMasks ? [] : json_decode($this->photoMasks, true, 4, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('Masks must be a list.');
        }

        return array_values(array_map(static fn (mixed $list): array => PlateMask::listFromArray($list), $decoded));
    }
}
