<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\DenyMediaDeletionCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class DenyMediaDeletionCommandHandler implements CommandHandlerInterface
{
    public function __construct(private IncidentRepositoryInterface $incidents)
    {
    }

    public function __invoke(DenyMediaDeletionCommand $command): void
    {
        $this->incidents->get($command->incidentId)->denyMediaDeletion();
        $this->incidents->flush();
    }
}
