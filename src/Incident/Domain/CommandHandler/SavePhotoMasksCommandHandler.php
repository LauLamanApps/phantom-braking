<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\SavePhotoMasksCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\PhotoPublisher;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class SavePhotoMasksCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private PhotoPublisher $photoPublisher,
    ) {
    }

    public function __invoke(SavePhotoMasksCommand $command): void
    {
        $incident = $this->incidents->getByPhoto($command->photoId);
        $this->photoPublisher->publishMasked($incident, $command->photoId, $command->masks);
        $this->incidents->flush();
    }
}
