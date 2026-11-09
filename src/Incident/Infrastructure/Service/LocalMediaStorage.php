<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Exception\RangeNotSatisfiableException;
use App\Incident\Domain\Service\MediaObject;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\StorageUsage;
use App\Incident\Domain\ValueObject\ByteRange;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\MediaKind;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/** Media on the local disk, outside the web root. Used by the test suite; production uses S3. */
final readonly class LocalMediaStorage implements MediaStorageInterface
{
    private const int CHUNK = 65536;

    public function __construct(
        #[Autowire('%env(resolve:FOOTAGE_STORAGE_DIR)%')]
        private string $directory,
        private Filesystem $filesystem,
    ) {
    }

    public function store(IncidentId $incidentId, string $name, string $temporaryPath, string $contentType): string
    {
        $key = MediaKey::for($incidentId, $name);

        $this->filesystem->mkdir(\dirname($this->path($key)));
        // Overwrite: a photo's public copy is regenerated under the same name when masks change.
        $this->filesystem->rename($temporaryPath, $this->path($key), overwrite: true);

        return $key;
    }

    public function open(string $key, ?ByteRange $range = null): MediaObject
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            throw new MediaNotFoundException('No such media.');
        }

        $size = (int) filesize($path);
        $bytes = null === $range ? [0, $size - 1] : ($range->resolve($size) ?? throw new RangeNotSatisfiableException($size));

        return new MediaObject(self::read($path, $bytes[0], $bytes[1] - $bytes[0] + 1), max(0, $bytes[1] - $bytes[0] + 1), $size, null === $range ? null : $bytes);
    }

    public function withLocalCopy(string $key, callable $operation): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            throw new MediaNotFoundException('No such media.');
        }

        return $operation($path);
    }

    public function delete(string $key): void
    {
        $this->filesystem->remove($this->path($key));
    }

    public function usage(): array
    {
        $totals = [];
        foreach (MediaKind::cases() as $kind) {
            $totals[$kind->value] = [0, 0];
        }
        if (is_dir($this->directory)) {
            foreach ((new Finder())->files()->in($this->directory) as $file) {
                $kind = MediaKind::fromKey($file->getFilename())->value;
                ++$totals[$kind][0];
                $totals[$kind][1] += (int) $file->getSize();
            }
        }

        return array_map(fn (MediaKind $kind): StorageUsage => new StorageUsage($kind, $this->directory, $totals[$kind->value][0], $totals[$kind->value][1]), MediaKind::cases());
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/').'/'.MediaKey::assertSafe($key);
    }

    /** @return \Generator<string> */
    private static function read(string $path, int $offset, int $length): \Generator
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new MediaNotFoundException('Media could not be opened.');
        }
        try {
            fseek($handle, $offset);
            while ($length > 0 && !feof($handle)) {
                $chunk = (string) fread($handle, min(self::CHUNK, $length));
                $length -= \strlen($chunk);
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }
}
