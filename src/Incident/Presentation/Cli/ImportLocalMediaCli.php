<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Infrastructure\Service\S3MediaStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;

/** One-off: upload media stored on local disk (before S3) under the same keys, so database paths stay valid. */
#[AsCommand(name: 'app:media:import-local', description: 'Copy media from the local storage directory into the S3 bucket (same keys).')]
final readonly class ImportLocalMediaCli
{
    public function __construct(
        private S3MediaStorage $storage,
        #[Autowire('%env(resolve:FOOTAGE_STORAGE_DIR)%')]
        private string $localDirectory,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Delete local files after a successful upload')] bool $deleteLocal = false): int
    {
        if (!is_dir($this->localDirectory)) {
            $io->success('No local media directory; nothing to import.');

            return Command::SUCCESS;
        }

        $mimeTypes = new MimeTypes();
        $count = 0;
        foreach ((new Finder())->files()->in($this->localDirectory) as $file) {
            $key = str_replace('\\', '/', $file->getRelativePathname());
            $this->storage->put($key, $file->getPathname(), $mimeTypes->guessMimeType($file->getPathname()) ?? 'application/octet-stream');
            if ($deleteLocal) {
                unlink($file->getPathname());
            }
            ++$count;
        }

        $io->success(sprintf('Imported %d files into the media bucket%s.', $count, $deleteLocal ? ' and deleted them locally' : ''));

        return Command::SUCCESS;
    }
}
