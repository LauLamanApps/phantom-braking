<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Service\MediaObject;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\StorageUsage;
use App\Incident\Domain\ValueObject\ByteRange;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\MediaKind;

final class InMemoryMediaStorage implements MediaStorageInterface
{
    /** @var array<string, string> storage key → original temporary path */
    public array $files = [];

    public function store(IncidentId $incidentId, string $name, string $temporaryPath, string $contentType): string
    {
        $key = $incidentId.'/'.$name;
        $this->files[$key] = $temporaryPath;

        return $key;
    }

    public function open(string $key, ?ByteRange $range = null): MediaObject
    {
        $content = $this->files[$key] ?? throw new MediaNotFoundException($key);

        return new MediaObject([$content], \strlen($content), \strlen($content));
    }

    public function withLocalCopy(string $key, callable $operation): mixed
    {
        return $operation('/memory/'.$key);
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }

    public function usage(): array
    {
        return array_map(fn (MediaKind $kind): StorageUsage => new StorageUsage(
            $kind,
            'memory',
            \count(array_filter(array_keys($this->files), static fn (string $key): bool => MediaKind::fromKey($key) === $kind)),
            0,
        ), MediaKind::cases());
    }
}
