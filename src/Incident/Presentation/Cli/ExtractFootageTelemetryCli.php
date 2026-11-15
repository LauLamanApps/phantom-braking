<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Domain\Command\ExtractFootageTelemetryCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:footage:extract-telemetry',
    description: 'Read the driving data (speed, pedals, steering, …) embedded in dashcam footage that has not been read yet, e.g. from before this existed.',
)]
final readonly class ExtractFootageTelemetryCli
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('At most this many videos per run (each one is downloaded from storage)')] int $limit = 100): int
    {
        $ids = $this->incidents->getIdsNeedingFootageTelemetry($limit);

        $io->progressStart(\count($ids));
        foreach ($ids as $id) {
            $this->commandBus->dispatch(new ExtractFootageTelemetryCommand($id));
            $io->progressAdvance();
        }
        $io->progressFinish();

        $found = array_filter($ids, fn ($id): bool => $this->incidents->get($id)->getFootage()->hasTelemetry());
        $io->success(sprintf('Read %d videos, %d of them with driving data.', \count($ids), \count($found)));

        return Command::SUCCESS;
    }
}
