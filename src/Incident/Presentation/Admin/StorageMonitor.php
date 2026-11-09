<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Admin;

use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\StorageUsage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Bucket sizes for the admin dashboard. Listing every object is slow and S3 has
 * no "bucket size" call, so the result is cached for a while.
 */
final readonly class StorageMonitor
{
    private const string CACHE_KEY = 'admin_storage_usage';
    private const int CACHE_TTL = 900;

    /*
     * Sliplane Object Storage pricing (https://sliplane.io/pricing#object-storage, November 2026):
     * the first GB is free, then €5 per started block of 250 GB, billed on the monthly average.
     */
    private const int FREE_BYTES = 1_000_000_000;
    private const int BLOCK_BYTES = 250_000_000_000;
    private const int EUR_PER_BLOCK = 5;

    public function __construct(
        private MediaStorageInterface $storage,
        private CacheInterface $cache,
    ) {
    }

    /** @return array{usage: list<StorageUsage>, totalBytes: int, estimatedEurPerMonth: int, measuredAt: \DateTimeImmutable} */
    public function report(): array
    {
        /** @var array{usage: list<StorageUsage>, measuredAt: \DateTimeImmutable} $measured */
        $measured = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return ['usage' => $this->storage->usage(), 'measuredAt' => new \DateTimeImmutable()];
        });

        $total = array_sum(array_map(static fn (StorageUsage $u): int => $u->bytes, $measured['usage']));

        return $measured + ['totalBytes' => $total, 'estimatedEurPerMonth' => self::estimate($total)];
    }

    public function refresh(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    public static function estimate(int $bytes): int
    {
        return $bytes <= self::FREE_BYTES ? 0 : (int) ceil(($bytes - self::FREE_BYTES) / self::BLOCK_BYTES) * self::EUR_PER_BLOCK;
    }
}
