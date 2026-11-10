<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Exception\MediaUnavailableException;
use App\Incident\Domain\Exception\RangeNotSatisfiableException;
use App\Incident\Domain\ValueObject\ByteRange;
use App\Incident\Domain\ValueObject\IncidentId;

/**
 * Private storage for uploaded media (dashcam footage, photos), kept apart per MediaKind.
 * Nothing in it is ever reachable directly: the stream controllers decide what may be served.
 */
interface MediaStorageInterface
{
    /**
     * Moves a temporary file into storage (the temporary file is consumed) and returns its key.
     *
     * @param string $name file name within the incident's folder, e.g. "footage.mp4"
     */
    public function store(IncidentId $incidentId, string $name, string $temporaryPath, string $contentType): string;

    /**
     * @throws MediaNotFoundException
     * @throws RangeNotSatisfiableException
     * @throws MediaUnavailableException
     */
    public function open(string $key, ?ByteRange $range = null): MediaObject;

    /**
     * Runs $operation with a local file path of the stored object (e.g. for image processing),
     * and cleans up any temporary copy afterwards.
     *
     * @template T
     *
     * @param callable(string): T $operation
     *
     * @return T
     *
     * @throws MediaNotFoundException
     */
    public function withLocalCopy(string $key, callable $operation): mixed;

    public function delete(string $key): void;

    /**
     * What is stored right now, per kind of media. Can be slow (lists all objects): cache the result.
     *
     * @return list<StorageUsage>
     */
    public function usage(): array;
}
