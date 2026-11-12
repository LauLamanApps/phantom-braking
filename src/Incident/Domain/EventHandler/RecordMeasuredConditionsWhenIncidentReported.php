<?php

declare(strict_types=1);

namespace App\Incident\Domain\EventHandler;

use App\Incident\Domain\Command\RecordMeasuredConditionsCommand;
use App\Incident\Domain\Event\IncidentReportedEvent;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Messenger\Handler\EventHandlerInterface;

/** Runs in the worker, so a slow weather service never holds up the reporter. */
final readonly class RecordMeasuredConditionsWhenIncidentReported implements EventHandlerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(IncidentReportedEvent $event): void
    {
        $this->commandBus->dispatch(new RecordMeasuredConditionsCommand($event->incidentId));
    }
}
