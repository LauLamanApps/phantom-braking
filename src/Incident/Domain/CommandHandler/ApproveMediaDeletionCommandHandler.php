<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ApproveMediaDeletionCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

/** An admin approved the reporter's request: delete all footage and photo files of the report. */
final readonly class ApproveMediaDeletionCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private MediaStorageInterface $mediaStorage,
    ) {
    }

    public function __invoke(ApproveMediaDeletionCommand $command): void
    {
        $incident = $this->incidents->get($command->incidentId);
        if (null === $incident->getMediaDeletionRequestedAt()) {
            throw new \DomainException('There is no deletion request for this report.');
        }

        $keys = $incident->deleteAllMedia(new \DateTimeImmutable());
        $this->incidents->flush();

        // Files go only after the database no longer points at them.
        foreach ($keys as $key) {
            $this->mediaStorage->delete($key);
        }
    }
}
