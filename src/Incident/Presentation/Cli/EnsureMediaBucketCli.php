<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Infrastructure\Service\S3MediaStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:ensure-bucket', description: 'Create the private photo and video buckets if they do not exist yet.')]
final readonly class EnsureMediaBucketCli
{
    public function __construct(private S3MediaStorage $storage)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $created = $this->storage->ensureBuckets();
        $io->success([] === $created ? 'Media buckets already exist.' : 'Created: '.implode(', ', $created).'.');

        return Command::SUCCESS;
    }
}
