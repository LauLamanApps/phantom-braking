<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Domain\Command\RecordMeasuredConditionsCommand;
use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsCommand(
    name: 'app:incident:fill-conditions',
    description: 'Calculate the sun and look up the measured weather for reports that miss them (older reports, or a failed lookup).',
)]
final readonly class FillMeasuredConditionsCli
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('At most this many reports per run (Open-Meteo allows 10,000 lookups a day)')] int $limit = 500): int
    {
        $ids = $this->incidents->getIdsNeedingMeasuredConditions($limit);
        $failed = 0;

        $io->progressStart(\count($ids));
        foreach ($ids as $id) {
            try {
                $this->commandBus->dispatch(new RecordMeasuredConditionsCommand($id));
            } catch (HandlerFailedException $e) {
                if (!$e->getPrevious() instanceof WeatherHistoryUnavailableException) {
                    throw $e;
                }
                ++$failed;
            }
            $io->progressAdvance();
        }
        $io->progressFinish();

        if ($failed > 0) {
            $io->warning(sprintf('Open-Meteo could not be reached for %d of %d reports; run this again later.', $failed, \count($ids)));

            return Command::FAILURE;
        }
        $io->success(sprintf('Filled in %d reports.', \count($ids)));

        return Command::SUCCESS;
    }
}
