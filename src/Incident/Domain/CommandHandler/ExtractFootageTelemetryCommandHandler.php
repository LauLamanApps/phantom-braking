<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\ExtractFootageTelemetryCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\FootageTelemetryReaderInterface;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\ValueObject\FootageTelemetry;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class ExtractFootageTelemetryCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private MediaStorageInterface $mediaStorage,
        private FootageTelemetryReaderInterface $reader,
    ) {
    }

    public function __invoke(ExtractFootageTelemetryCommand $command): void
    {
        $incident = $this->incidents->get($command->incidentId);
        $footage = $incident->getFootage();
        if (!$footage->needsTelemetryCheck()) {
            return;
        }

        $telemetry = $this->mediaStorage->withLocalCopy((string) $footage->getPath(), fn (string $path): ?FootageTelemetry => $this->reader->read($path));

        $key = null;
        if (null !== $telemetry && !$telemetry->isEmpty()) {
            $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'telemetry_');
            file_put_contents($temporaryPath, $telemetry->toJson());
            $key = $this->mediaStorage->store($incident->getId(), 'footage-telemetry.json', $temporaryPath, 'application/json');
        }

        $incident->recordFootageTelemetry($key, new \DateTimeImmutable());
        $this->incidents->flush();
    }
}
