<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\Exception\AssistSystemNotAvailableException;
use App\Incident\Domain\Model\DashcamFootage;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Model\Location;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Country;
use PHPUnit\Framework\TestCase;

final class IncidentTest extends TestCase
{
    public function testDerivesReportingColumnsAndSpeedDrop(): void
    {
        $incident = self::incident(new \DateTimeImmutable('2026-03-14 07:45'), 120, 40);

        self::assertSame(80, $incident->getSpeedDrop());
        self::assertSame('MAZDA', $incident->getVehicle()->getMake());
        self::assertSame('A15', $incident->getLocation()->getRoadNumber());
        self::assertSame(51.9123, $incident->getLocation()->getLatitude());
    }

    public function testIncidentCountryIsAnyEuropeanCountry(): void
    {
        self::assertSame('BE', IncidentCountry::fromString(' be ')->code);
        self::assertNull(IncidentCountry::tryFromString('US'));

        $this->expectException(\InvalidArgumentException::class);
        IncidentCountry::fromString('XX');
    }

    public function testRejectsSpeedAfterAboveSpeedBefore(): void
    {
        $this->expectException(\DomainException::class);
        self::incident(new \DateTimeImmutable('-1 day'), 80, 100);
    }

    public function testRejectsIncidentInTheFuture(): void
    {
        $this->expectException(\DomainException::class);
        self::incident(new \DateTimeImmutable('+1 day'), 80, 0);
    }

    public function testFootageNeedsConsentBeforeItCanBePublished(): void
    {
        $incident = self::incident(new \DateTimeImmutable('-1 day'), 100, 30);
        $incident->attachFootage(new DashcamFootage('2026/03/x.mp4', 'video/mp4', 1000, false));

        self::assertSame(ModerationStatus::Pending, $incident->getFootage()->getStatus());
        self::assertFalse($incident->getFootage()->isPublic());

        $this->expectException(\DomainException::class);
        $incident->approveFootage();
    }

    public function testConsentedFootageCanBeApproved(): void
    {
        $incident = self::incident(new \DateTimeImmutable('-1 day'), 100, 30);
        $incident->attachFootage(new DashcamFootage('2026/03/x.mp4', 'video/mp4', 1000, true));
        $incident->approveFootage();

        self::assertTrue($incident->getFootage()->isPublic());
    }

    public function testPhotosAreLimitedAndNeedConsentAndAMaskedCopyToBePublished(): void
    {
        $incident = self::incident(new \DateTimeImmutable('-1 day'), 100, 30);
        for ($i = 0; $i < Incident::MAX_PHOTOS; ++$i) {
            $incident->addPhoto(PhotoId::generate(), "p$i.jpg", 'image/jpeg', 1, 1, 1, 0 === $i);
        }
        [$consented, $notConsented] = $incident->getPhotos();

        try {
            $incident->approvePhoto($consented->getId());
            self::fail('A photo without masked copy must not be approvable');
        } catch (\DomainException) {
            self::assertFalse($consented->isPublic());
        }

        $incident->maskPhoto($consented->getId(), [PlateMask::fromArray([0.4, 0.7, 0.1, 0.05])], 'p0-public.jpg');
        $incident->approvePhoto($consented->getId());
        self::assertSame([$consented], $incident->getPublicPhotos());
        self::assertSame('p0-public.jpg', $consented->getPublishedPath());
        self::assertSame([[0.4, 0.7, 0.1, 0.05]], array_map(static fn (PlateMask $m): array => $m->toArray(), $consented->getMasks()));

        $incident->maskPhoto($notConsented->getId(), [], 'p1-public.jpg');
        try {
            $incident->approvePhoto($notConsented->getId());
            self::fail('Approving without consent must fail');
        } catch (\DomainException) {
            self::assertFalse($notConsented->isPublic());
        }

        $this->expectException(\DomainException::class);
        $incident->addPhoto(PhotoId::generate(), 'one-too-many.jpg', 'image/jpeg', 1, 1, 1, true);
    }

    public function testDeletionRequestLifecycle(): void
    {
        $incident = self::incident(new \DateTimeImmutable('-1 day'), 100, 30);
        try {
            $incident->requestMediaDeletion(new \DateTimeImmutable());
            self::fail('Nothing to delete without media');
        } catch (\DomainException) {
            self::assertNull($incident->getMediaDeletionRequestedAt());
        }

        $incident->attachFootage(new DashcamFootage('x/footage.mp4', 'video/mp4', 1000, true));
        $photo = $incident->addPhoto(PhotoId::generate(), 'x/p.jpg', 'image/jpeg', 1, 1, 1, true);
        $incident->maskPhoto($photo->getId(), [], 'x/p-public.jpg');

        $first = new \DateTimeImmutable('-1 hour');
        $incident->requestMediaDeletion($first);
        $incident->requestMediaDeletion(new \DateTimeImmutable());
        self::assertEquals($first, $incident->getMediaDeletionRequestedAt(), 'the first request counts');

        $incident->denyMediaDeletion();
        self::assertNull($incident->getMediaDeletionRequestedAt());
        self::assertTrue($incident->hasMedia(), 'denying keeps the media');

        $incident->requestMediaDeletion(new \DateTimeImmutable());
        self::assertSame(['x/footage.mp4', 'x/p.jpg', 'x/p-public.jpg'], $incident->deleteAllMedia(new \DateTimeImmutable()));
        self::assertFalse($incident->hasMedia());
        self::assertSame([], $incident->getPhotos());
        self::assertNull($incident->getMediaDeletionRequestedAt());
        self::assertNotNull($incident->getMediaDeletedAt());
    }

    public function testPlateMaskValidation(): void
    {
        self::assertSame([0.9, 0.0, 0.1, 0.2], PlateMask::fromArray([0.9, 0, 0.1, 0.2])->toArray());
        foreach ([[0.5, 0.5, 0.6, 0.1], [-0.1, 0, 0.1, 0.1], [0, 0, 0, 0.1], ['a', 0, 0.1, 0.1], [0, 0, 0.1]] as $bad) {
            try {
                PlateMask::fromArray($bad);
                self::fail('Expected rejection of '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testGsr2Applicability(): void
    {
        self::assertTrue((new VehicleSnapshot('A', 'B', new \DateTimeImmutable('2024-07-07')))->isSubjectToGsr2());
        self::assertFalse((new VehicleSnapshot('A', 'B', new \DateTimeImmutable('2024-07-06')))->isSubjectToGsr2());
        self::assertNull((new VehicleSnapshot('A', 'B'))->isSubjectToGsr2());
    }

    public function testOnlyCarsWithMandatoryAebAreToBeReportedToTheAuthority(): void
    {
        self::assertFalse(self::incident(new \DateTimeImmutable('-1 day'), 100, 40, new VehicleSnapshot('A', 'B', new \DateTimeImmutable('2022-01-01')))->shouldBeReportedToAuthority());
        self::assertFalse(self::incident(new \DateTimeImmutable('-1 day'), 100, 40, new VehicleSnapshot('A', 'B'))->shouldBeReportedToAuthority(), 'unknown admission date');

        $incident = self::incident(new \DateTimeImmutable('-1 day'), 100, 40);
        self::assertTrue($incident->shouldBeReportedToAuthority());

        $now = new \DateTimeImmutable();
        $incident->confirmReportedToAuthorityViaPrompt($now);
        self::assertTrue($incident->isReportedToAuthority());
        self::assertSame($now, $incident->getReportedToAuthorityViaPromptAt());
        self::assertFalse($incident->shouldBeReportedToAuthority());

        // Confirming again doesn't move the moment the prompt worked.
        $incident->confirmReportedToAuthorityViaPrompt(new \DateTimeImmutable('+1 hour'));
        self::assertSame($now, $incident->getReportedToAuthorityViaPromptAt());
    }

    public function testTeslaAssistedDrivingOnlyExistsOnATesla(): void
    {
        $tesla = new VehicleSnapshot('Tesla', 'Model Y');
        self::assertSame(ActiveAssistSystem::TeslaAssistedDriving, self::incident(new \DateTimeImmutable('-1 day'), 100, 40, $tesla, ActiveAssistSystem::TeslaAssistedDriving)->getAssistSystem());
        self::assertTrue(ActiveAssistSystem::AdaptiveCruise->isAvailableFor('TESLA'));

        $this->expectException(AssistSystemNotAvailableException::class);
        self::incident(new \DateTimeImmutable('-1 day'), 100, 40, assist: ActiveAssistSystem::TeslaAssistedDriving);
    }

    private static function incident(
        \DateTimeImmutable $occurredAt,
        int $before,
        ?int $after,
        ?VehicleSnapshot $vehicle = null,
        ActiveAssistSystem $assist = ActiveAssistSystem::AdaptiveCruise,
    ): Incident {
        return new Incident(
            id: IncidentId::generate(),
            country: Country::Netherlands,
            plateFingerprint: str_repeat('a', 64),
            vehicle: $vehicle ?? new VehicleSnapshot(' mazda ', 'cx-60', new \DateTimeImmutable('2024-10-01')),
            occurredAt: $occurredAt,
            location: new Location(IncidentCountry::fromString('nl'), RoadType::Motorway, RoadSituation::UnderViaduct, 'a 15', 51.91234, 5.84321),
            severity: BrakeSeverity::Emergency,
            speedBefore: $before,
            speedAfter: $after,
            trigger: SuspectedTrigger::ViaductOrGantry,
            assistSystem: $assist,
            warningShown: true,
            weather: Weather::Dry,
            light: LightCondition::Daylight,
            consequence: Consequence::NearRearEnd,
            recurrence: Recurrence::Few,
            dealerResponse: DealerResponse::NoFaultFound,
            reportedToAuthority: false,
            windscreenReplacedRecently: null,
            odometerKm: null,
            softwareVersion: null,
            description: null,
            reportedAt: new \DateTimeImmutable(),
        );
    }
}
