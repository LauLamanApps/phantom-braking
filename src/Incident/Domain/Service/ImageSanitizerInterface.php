<?php

declare(strict_types=1);

namespace App\Incident\Domain\Service;

use App\Incident\Domain\Exception\InvalidImageException;

/**
 * Turns an uploaded image into a safe one to store and publish: decoded and
 * re-encoded (so anything that is not really an image is rejected), upright,
 * scaled down, and stripped of all metadata such as EXIF GPS coordinates.
 */
interface ImageSanitizerInterface
{
    /**
     * Consumes the upload: on success the original file (with its metadata) is deleted.
     *
     * @throws InvalidImageException
     */
    public function sanitize(string $path): SanitizedImage;
}
