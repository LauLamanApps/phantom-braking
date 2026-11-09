<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ReportIncidentCommand;
use App\Incident\Domain\Event\IncidentReportedEvent;
use App\Incident\Domain\Model\DashcamFootage;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Model\Location;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\ImageSanitizerInterface;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\PhotoPublisher;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Messenger\Bus\EventBusInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;
use App\Vehicle\Domain\Service\PlateFingerprinterInterface;
use App\Vehicle\Domain\Service\VehicleRegistryInterface;
use App\Vehicle\Domain\ValueObject\LicensePlate;

final readonly class ReportIncidentCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private VehicleRegistryInterface $vehicleRegistry,
        private PlateFingerprinterInterface $fingerprinter,
        private MediaStorageInterface $mediaStorage,
        private ImageSanitizerInterface $imageSanitizer,
        private PhotoPublisher $photoPublisher,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ReportIncidentCommand $command): void
    {
        $plate = LicensePlate::fromInput($command->licensePlate);
        $vehicle = $this->vehicleRegistry->lookup($command->country, $plate);

        $incident = new Incident(
            id: $command->incidentId,
            country: $command->country,
            plateFingerprint: $this->fingerprinter->fingerprint($command->country, $plate),
            vehicle: $vehicle,
            occurredAt: $command->occurredAt,
            location: new Location(
                country: $command->incidentCountry,
                roadType: $command->roadType,
                situation: $command->roadSituation,
                roadNumber: $command->roadNumber,
                latitude: $command->latitude,
                longitude: $command->longitude,
                direction: $command->direction,
                speedLimit: $command->speedLimit,
            ),
            severity: $command->severity,
            speedBefore: $command->speedBefore,
            speedAfter: $command->speedAfter,
            trigger: $command->trigger,
            assistSystem: $command->assistSystem,
            warningShown: $command->warningShown,
            weather: $command->weather,
            light: $command->light,
            consequence: $command->consequence,
            recurrence: $command->recurrence,
            dealerResponse: $command->dealerResponse,
            reportedToAuthority: $command->reportedToAuthority,
            windscreenReplacedRecently: $command->windscreenReplacedRecently,
            odometerKm: $command->odometerKm,
            softwareVersion: $command->softwareVersion,
            description: $command->description,
            reportedAt: new \DateTimeImmutable(),
        );

        // Sanitise every photo before anything is stored, so one bad file rejects the report cleanly.
        $photos = array_map(fn ($upload): array => [$upload, $this->imageSanitizer->sanitize($upload->temporaryPath)], $command->photos);

        if (null !== $command->footage) {
            $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $command->footage->extension)) ?: 'bin';
            $incident->attachFootage(new DashcamFootage(
                path: $this->mediaStorage->store($incident->getId(), 'footage.'.$extension, $command->footage->temporaryPath, $command->footage->mimeType),
                mimeType: $command->footage->mimeType,
                size: $command->footage->size,
                publicationConsent: $command->footage->publicationConsent,
            ));
        }

        foreach ($photos as [$upload, $image]) {
            $photoId = PhotoId::generate();
            $incident->addPhoto(
                id: $photoId,
                path: $this->mediaStorage->store($incident->getId(), sprintf('photo-%s.%s', $photoId, $image->extension), $image->path, $image->mimeType),
                mimeType: $image->mimeType,
                size: $image->size,
                width: $image->width,
                height: $image->height,
                publicationConsent: $upload->publicationConsent,
            );
            // The masked copy is what gets published; the reporter's masks are a first pass, the moderator checks them.
            $this->photoPublisher->publishMasked($incident, $photoId, $upload->masks);
        }

        $this->incidents->persist($incident);
        $this->incidents->flush();

        $this->eventBus->dispatch(new IncidentReportedEvent($incident->getId(), null !== $command->footage, \count($command->photos)));
    }
}
