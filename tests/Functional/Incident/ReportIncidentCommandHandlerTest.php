<?php

declare(strict_types=1);

namespace App\Tests\Functional\Incident;

use App\Incident\Domain\Command\ApproveMediaDeletionCommand;
use App\Incident\Domain\Command\FootageUpload;
use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Command\PhotoUpload;
use App\Incident\Domain\Command\ReportIncidentCommand;
use App\Incident\Domain\Command\RequestMediaDeletionCommand;
use App\Incident\Domain\Command\SavePhotoMasksCommand;
use App\Incident\Domain\CommandHandler\ApproveMediaDeletionCommandHandler;
use App\Incident\Domain\CommandHandler\ModerateFootageCommandHandler;
use App\Incident\Domain\CommandHandler\ModeratePhotoCommandHandler;
use App\Incident\Domain\CommandHandler\ReportIncidentCommandHandler;
use App\Incident\Domain\CommandHandler\RequestMediaDeletionCommandHandler;
use App\Incident\Domain\CommandHandler\SavePhotoMasksCommandHandler;
use App\Incident\Domain\Event\IncidentReportedEvent;
use App\Incident\Domain\Exception\InvalidImageException;
use App\Incident\Domain\Exception\PlateMismatchException;
use App\Incident\Domain\Service\PhotoPublisher;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Tests\Double\FakeImageRedactor;
use App\Tests\Double\FakeImageSanitizer;
use App\Tests\Double\InMemoryIncidentRepository;
use App\Tests\Double\InMemoryMediaStorage;
use App\Tests\Double\InMemoryVehicleRegistry;
use App\Tests\Double\SpyEventBus;
use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Service\PlateFingerprinterInterface;
use App\Vehicle\Domain\ValueObject\Country;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use App\Vehicle\Infrastructure\Service\HmacPlateFingerprinter;
use PHPUnit\Framework\TestCase;

final class ReportIncidentCommandHandlerTest extends TestCase
{
    private InMemoryIncidentRepository $incidents;
    private InMemoryMediaStorage $storage;
    private SpyEventBus $events;
    private PlateFingerprinterInterface $fingerprinter;
    private ReportIncidentCommandHandler $handler;
    private FakeImageRedactor $redactor;

    protected function setUp(): void
    {
        $this->incidents = new InMemoryIncidentRepository();
        $this->storage = new InMemoryMediaStorage();
        $this->events = new SpyEventBus();
        $this->fingerprinter = new HmacPlateFingerprinter('functional-test-key');
        $this->handler = new ReportIncidentCommandHandler(
            $this->incidents,
            new InMemoryVehicleRegistry(),
            $this->fingerprinter,
            $this->storage,
            new FakeImageSanitizer(),
            new PhotoPublisher($this->storage, $this->redactor = new FakeImageRedactor()),
            $this->events,
        );
    }

    public function testStoresIncidentWithRegistryVehicleAndFingerprintOnly(): void
    {
        $id = IncidentId::generate();
        ($this->handler)(self::command($id, 'te-123-s'));

        $incident = $this->incidents->get($id);
        self::assertSame('TESLA', $incident->getVehicle()->getMake());
        self::assertSame('MODEL 3', $incident->getVehicle()->getModel());
        self::assertSame(
            $this->fingerprinter->fingerprint(Country::Netherlands, LicensePlate::fromInput('TE123S')),
            $incident->getPlateFingerprint(),
        );
        self::assertFalse($incident->getFootage()->exists());
        // Registered in NL, happened in DE: the two are kept apart.
        self::assertSame(Country::Netherlands, $incident->getCountry());
        self::assertSame('DE', $incident->getLocation()->getCountry()->code);
        self::assertSame(1, $this->incidents->flushes);

        self::assertCount(1, $this->events->dispatched);
        $event = $this->events->dispatched[0];
        self::assertInstanceOf(IncidentReportedEvent::class, $event);
        self::assertTrue($id->equals($event->incidentId));
        self::assertFalse($event->hasFootage);
    }

    public function testSameVehicleGetsSameFingerprintRegardlessOfNotation(): void
    {
        ($this->handler)(self::command($a = IncidentId::generate(), 'TE-123-S'));
        ($this->handler)(self::command($b = IncidentId::generate(), 'te 123 s'));

        self::assertSame($this->incidents->get($a)->getPlateFingerprint(), $this->incidents->get($b)->getPlateFingerprint());
    }

    public function testAttachesFootagePendingModeration(): void
    {
        $id = IncidentId::generate();
        ($this->handler)(self::command($id, 'VW001X', new FootageUpload('/tmp/upload', 'mp4', 'video/mp4', 4096, true)));

        $footage = $this->incidents->get($id)->getFootage();
        self::assertSame($id.'/footage.mp4', $footage->getPath());
        self::assertSame(ModerationStatus::Pending, $footage->getStatus());
        self::assertArrayHasKey($id.'/footage.mp4', $this->storage->files);
        $event = $this->events->dispatched[0];
        self::assertInstanceOf(IncidentReportedEvent::class, $event);
        self::assertTrue($event->hasFootage);

        (new ModerateFootageCommandHandler($this->incidents))(new ModerateFootageCommand($id, true));
        self::assertTrue($this->incidents->get($id)->getFootage()->isPublic());
    }

    public function testStoresSanitisedPhotosPrivatelyUntilApproved(): void
    {
        $id = IncidentId::generate();
        ($this->handler)(self::command($id, 'TE123S', photos: [
            new PhotoUpload('/tmp/upload-1', true),
            new PhotoUpload('/tmp/upload-2', true),
        ]));

        $incident = $this->incidents->get($id);
        $photos = $incident->getPhotos();
        self::assertCount(2, $photos);
        self::assertSame([0, 1], [$photos[0]->getPosition(), $photos[1]->getPosition()]);
        self::assertSame($id.'/photo-'.$photos[0]->getId().'.jpg', $photos[0]->getPath());
        // The stored file is the sanitised one, never the upload.
        self::assertSame('/tmp/upload-1.clean', $this->storage->files[$photos[0]->getPath()]);
        self::assertNotNull($photos[0]->getPublishedPath(), 'a public copy is made right away');
        self::assertSame([], $incident->getPublicPhotos());

        $event = $this->events->dispatched[0];
        self::assertInstanceOf(IncidentReportedEvent::class, $event);
        self::assertSame(2, $event->photoCount);

        (new ModeratePhotoCommandHandler($this->incidents))(new ModeratePhotoCommand($photos[1]->getId(), true));
        self::assertSame([$photos[1]], $this->incidents->get($id)->getPublicPhotos());
    }

    public function testOneUnreadablePhotoRejectsTheWholeReportBeforeAnythingIsStored(): void
    {
        $thrown = null;
        try {
            ($this->handler)(self::command(IncidentId::generate(), 'TE123S', new FootageUpload('/tmp/upload', 'mp4', 'video/mp4', 1, true), [
                new PhotoUpload('/tmp/ok', true),
                new PhotoUpload('/tmp/broken', true),
            ]));
        } catch (InvalidImageException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(InvalidImageException::class, $thrown);
        self::assertSame([], $this->storage->files);
        self::assertSame(0, $this->incidents->count(IncidentFilter::all()));
    }

    public function testReporterMasksAreBurntIntoThePublicCopy(): void
    {
        $id = IncidentId::generate();
        ($this->handler)(self::command($id, 'TE123S', photos: [
            new PhotoUpload('/tmp/upload-1', true, [PlateMask::fromArray([0.4, 0.7, 0.1, 0.05])]),
            new PhotoUpload('/tmp/upload-2', true),
        ]));

        [$masked, $unmasked] = $this->incidents->get($id)->getPhotos();
        self::assertSame($id.'/photo-'.$masked->getId().'-public.jpg', $masked->getPublishedPath());
        self::assertCount(1, $masked->getMasks());
        self::assertSame([['/memory/'.$masked->getPath(), 1], ['/memory/'.$unmasked->getPath(), 0]], $this->redactor->calls);
        self::assertSame('/memory/'.$masked->getPath().'.masked', $this->storage->files[(string) $masked->getPublishedPath()]);

        // A moderator adds a mask on the second photo: regenerated from the private original.
        (new SavePhotoMasksCommandHandler($this->incidents, new PhotoPublisher($this->storage, $this->redactor)))
            (new SavePhotoMasksCommand($unmasked->getId(), [PlateMask::fromArray([0.1, 0.1, 0.1, 0.05]), PlateMask::fromArray([0.5, 0.5, 0.1, 0.05])]));
        self::assertCount(2, $unmasked->getMasks());
        self::assertSame(['/memory/'.$unmasked->getPath(), 2], $this->redactor->lastCall());
    }

    public function testDeletionNeedsTheRightPlateAndAnAdminToDeleteTheFiles(): void
    {
        $id = IncidentId::generate();
        ($this->handler)(self::command($id, 'TE123S', new FootageUpload('/tmp/video', 'mp4', 'video/mp4', 1, true), [new PhotoUpload('/tmp/upload-1', true)]));
        self::assertCount(3, $this->storage->files, 'footage, photo original and public copy');

        $request = new RequestMediaDeletionCommandHandler($this->incidents, $this->fingerprinter);
        foreach (['VW001X', 'not a plate!'] as $wrong) {
            try {
                $request(new RequestMediaDeletionCommand($id, $wrong));
                self::fail('Expected PlateMismatchException for '.$wrong);
            } catch (PlateMismatchException) {
                self::assertNull($this->incidents->get($id)->getMediaDeletionRequestedAt());
            }
        }

        $request(new RequestMediaDeletionCommand($id, 'te-123-s'));
        self::assertNotNull($this->incidents->get($id)->getMediaDeletionRequestedAt());
        self::assertCount(3, $this->storage->files, 'nothing is deleted until an admin approves');

        (new ApproveMediaDeletionCommandHandler($this->incidents, $this->storage))(new ApproveMediaDeletionCommand($id));
        self::assertSame([], $this->storage->files);
        self::assertFalse($this->incidents->get($id)->hasMedia());
    }

    public function testUnknownVehicleStoresNothing(): void
    {
        $thrown = null;
        try {
            ($this->handler)(self::command(IncidentId::generate(), 'ZZ999Z', new FootageUpload('/tmp/upload', 'mp4', 'video/mp4', 1, false)));
        } catch (VehicleNotFoundException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(VehicleNotFoundException::class, $thrown);
        self::assertSame(0, $this->incidents->count(IncidentFilter::all()));
        self::assertSame([], $this->storage->files);
        self::assertSame([], $this->events->dispatched);
    }

    /** @param list<PhotoUpload> $photos */
    private static function command(IncidentId $id, string $plate, ?FootageUpload $footage = null, array $photos = []): ReportIncidentCommand
    {
        return new ReportIncidentCommand(
            incidentId: $id,
            country: Country::Netherlands,
            licensePlate: $plate,
            occurredAt: new \DateTimeImmutable('-1 day'),
            incidentCountry: IncidentCountry::fromString('DE'),
            roadType: RoadType::Motorway,
            roadSituation: RoadSituation::TunnelEntrance,
            roadNumber: 'A2',
            latitude: 51.9,
            longitude: 5.1,
            direction: null,
            speedLimit: 100,
            severity: BrakeSeverity::Strong,
            speedBefore: 100,
            speedAfter: 60,
            trigger: SuspectedTrigger::Shadow,
            assistSystem: ActiveAssistSystem::None,
            warningShown: false,
            weather: Weather::Dry,
            light: LightCondition::LowSun,
            consequence: Consequence::FollowerHadToBrake,
            recurrence: Recurrence::FirstTime,
            dealerResponse: DealerResponse::NotContacted,
            reportedToAuthority: false,
            windscreenReplacedRecently: null,
            odometerKm: null,
            softwareVersion: null,
            description: null,
            footage: $footage,
            photos: $photos,
        );
    }
}
