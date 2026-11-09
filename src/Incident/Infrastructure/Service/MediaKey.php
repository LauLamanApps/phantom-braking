<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\ValueObject\IncidentId;

/** Storage key layout shared by all media storage implementations: YYYY/MM/<incident>/<name>. */
final class MediaKey
{
    public static function for(IncidentId $incidentId, string $name): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9.\-]{0,99}$/', $name)) {
            throw new \InvalidArgumentException('Invalid media file name.');
        }

        return sprintf('%s/%s/%s', date('Y/m'), $incidentId, $name);
    }

    public static function assertSafe(string $key): string
    {
        if ('' === $key || str_contains($key, '..') || str_starts_with($key, '/')) {
            throw new \InvalidArgumentException('Invalid storage key.');
        }

        return $key;
    }
}
