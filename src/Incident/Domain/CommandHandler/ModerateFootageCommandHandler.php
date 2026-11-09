<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class ModerateFootageCommandHandler implements CommandHandlerInterface
{
    public function __construct(private IncidentRepositoryInterface $incidents)
    {
    }

    public function __invoke(ModerateFootageCommand $command): void
    {
        $incident = $this->incidents->get($command->incidentId);

        $command->approve ? $incident->approveFootage() : $incident->rejectFootage();

        $this->incidents->flush();
    }
}
