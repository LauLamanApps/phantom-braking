<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\RecordMeasuredConditionsCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\WeatherHistoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class RecordMeasuredConditionsCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private WeatherHistoryInterface $weatherHistory,
    ) {
    }

    public function __invoke(RecordMeasuredConditionsCommand $command): void
    {
        $incident = $this->incidents->get($command->incidentId);
        $incident->calculateSunPosition();

        if ($incident->needsMeasuredWeather()) {
            $location = $incident->getLocation();
            $incident->recordMeasuredWeather($this->weatherHistory->at((float) $location->getLatitude(), (float) $location->getLongitude(), $incident->getOccurredInstant()));
        }

        $this->incidents->flush();
    }
}
