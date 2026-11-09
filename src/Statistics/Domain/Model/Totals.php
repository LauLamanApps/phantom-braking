<?php

declare(strict_types=1);

namespace App\Statistics\Domain\Model;

final readonly class Totals
{
    public function __construct(
        public int $reports,
        public int $vehicles,
        public int $repeatVehicles,
        public int $makes,
        public int $actuallyBraked,
        public int $emergencyStops,
        public int $endangeredFollower,
        public int $collisions,
        public int $onMotorway,
        public int $atInfrastructure,
        public int $recurring,
        public int $dealerContacted,
        public int $dealerDismissive,
        public int $reportedToAuthority,
        public int $withFootage,
        public ?float $averageSpeedBefore,
        public ?float $averageSpeedDrop,
    ) {
    }

    public function share(int $part, ?int $of = null): ?float
    {
        $of ??= $this->reports;

        return 0 === $of ? null : round($part / $of * 100, 1);
    }
}
