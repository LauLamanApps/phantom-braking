<?php

declare(strict_types=1);

namespace App\Incident\Domain\EventHandler;

use App\Incident\Domain\Command\ExtractFootageTelemetryCommand;
use App\Incident\Domain\Event\IncidentReportedEvent;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Messenger\Handler\EventHandlerInterface;

/** Runs in the worker: reading through a large video never holds up the reporter. */
final readonly class ExtractFootageTelemetryWhenIncidentReported implements EventHandlerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(IncidentReportedEvent $event): void
    {
        if ($event->hasFootage) {
            $this->commandBus->dispatch(new ExtractFootageTelemetryCommand($event->incidentId));
        }
    }
}
