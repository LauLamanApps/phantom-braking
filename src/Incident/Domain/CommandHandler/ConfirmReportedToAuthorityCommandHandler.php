<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ConfirmReportedToAuthorityCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class ConfirmReportedToAuthorityCommandHandler implements CommandHandlerInterface
{
    public function __construct(private IncidentRepositoryInterface $incidents)
    {
    }

    public function __invoke(ConfirmReportedToAuthorityCommand $command): void
    {
        $this->incidents->get($command->incidentId)->confirmReportedToAuthorityViaPrompt(new \DateTimeImmutable());
        $this->incidents->flush();
    }
}
