<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Incident\Domain\Exception\InvalidImageException;
use App\Incident\Domain\Service\ImageSanitizerInterface;
use App\Incident\Domain\Service\SanitizedImage;

/** Pretends to re-encode; paths containing "broken" are rejected. */
final class FakeImageSanitizer implements ImageSanitizerInterface
{
    public function sanitize(string $path): SanitizedImage
    {
        if (str_contains($path, 'broken')) {
            throw new InvalidImageException('broken');
        }

        return new SanitizedImage($path.'.clean', 'image/jpeg', 'jpg', 800, 600, 1234);
    }
}
