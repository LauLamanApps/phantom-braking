<?php

declare(strict_types=1);

namespace App\Incident\Domain\Command;

use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\SharedKernel\Domain\Messenger\Transport\Command;
use App\Vehicle\Domain\ValueObject\Country;

final readonly class ReportIncidentCommand implements Command
{
    public function __construct(
        public IncidentId $incidentId,
        public Country $country,
        public string $licensePlate,
        public \DateTimeImmutable $occurredAt,
        public IncidentCountry $incidentCountry,
        public RoadType $roadType,
        public RoadSituation $roadSituation,
        public ?string $roadNumber,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $direction,
        public ?int $speedLimit,
        public BrakeSeverity $severity,
        public int $speedBefore,
        public ?int $speedAfter,
        public SuspectedTrigger $trigger,
        public ActiveAssistSystem $assistSystem,
        public ?bool $warningShown,
        public Weather $weather,
        public LightCondition $light,
        public Consequence $consequence,
        public Recurrence $recurrence,
        public DealerResponse $dealerResponse,
        public bool $reportedToAuthority,
        public ?bool $windscreenReplacedRecently,
        public ?int $odometerKm,
        public ?string $softwareVersion,
        public ?string $description,
        public ?FootageUpload $footage,
        /** @var list<PhotoUpload> */
        public array $photos = [],
    ) {
    }
}
