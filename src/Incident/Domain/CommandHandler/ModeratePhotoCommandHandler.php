<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class ModeratePhotoCommandHandler implements CommandHandlerInterface
{
    public function __construct(private IncidentRepositoryInterface $incidents)
    {
    }

    public function __invoke(ModeratePhotoCommand $command): void
    {
        $incident = $this->incidents->getByPhoto($command->photoId);

        $command->approve ? $incident->approvePhoto($command->photoId) : $incident->rejectPhoto($command->photoId);

        $this->incidents->flush();
    }
}
