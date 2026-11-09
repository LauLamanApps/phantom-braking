<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\ModerationStatus;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsCommand(
    name: 'app:media:moderate',
    description: 'List dashcam footage and photos awaiting review, or approve/reject one item.',
    help: <<<'HELP'
        List pending media:          <info>%command.full_name%</info>
        Approve a report's footage:  <info>%command.full_name% approve footage <incident-id></info>
        Reject a photo:              <info>%command.full_name% reject photo <photo-id></info>
        HELP,
)]
final readonly class ModerateMediaCli
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('approve|reject (omit to list pending media)')] ?string $action = null,
        #[Argument('footage|photo')] ?string $type = null,
        #[Argument('Incident ID (footage) or photo ID')] ?string $id = null,
    ): int {
        if (null === $action) {
            $this->list($io);

            return Command::SUCCESS;
        }

        if (!\in_array($action, ['approve', 'reject'], true) || !\in_array($type, ['footage', 'photo'], true) || null === $id) {
            $io->error('Usage: app:media:moderate [approve|reject footage|photo <id>]');

            return Command::INVALID;
        }

        try {
            $this->commandBus->dispatch('footage' === $type
                ? new ModerateFootageCommand(IncidentId::fromString($id), 'approve' === $action)
                : new ModeratePhotoCommand(PhotoId::fromString($id), 'approve' === $action));
        } catch (HandlerFailedException $e) {
            $io->error($e->getPrevious()?->getMessage() ?? $e->getMessage());

            return Command::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf('%s %s %sd.', ucfirst($type), $id, $action));

        return Command::SUCCESS;
    }

    private function list(SymfonyStyle $io): void
    {
        $rows = [];
        foreach ($this->incidents->getWithPendingMedia() as $incident) {
            $vehicle = $incident->getVehicle()->getMake().' '.$incident->getVehicle()->getModel();
            $footage = $incident->getFootage();
            if (ModerationStatus::Pending === $footage->getStatus()) {
                $rows[] = ['footage', (string) $incident->getId(), $vehicle, $footage->getPath(), round(($footage->getSize() ?? 0) / 1048576, 1).' MB', $footage->hasPublicationConsent() ? 'yes' : 'no'];
            }
            foreach ($incident->getPhotos() as $photo) {
                if (ModerationStatus::Pending === $photo->getStatus()) {
                    $rows[] = ['photo', (string) $photo->getId(), $vehicle, $photo->getPath(), $photo->getWidth().'×'.$photo->getHeight(), $photo->hasPublicationConsent() ? 'yes' : 'no'];
                }
            }
        }

        $io->table(['Type', 'ID to moderate', 'Vehicle', 'File', 'Size', 'Consent'], $rows);
        $io->note('Files are under FOOTAGE_STORAGE_DIR. Without consent, media can only be rejected (kept private).');
    }
}
