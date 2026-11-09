<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Service\ImageRedactorInterface;

/** Solid black boxes (not blur: blurred plates can sometimes be reconstructed). */
final class GdImageRedactor implements ImageRedactorInterface
{
    /** Each box is grown a little so a slightly-off rectangle still covers the plate. */
    private const float MARGIN = 0.005;

    public function redact(string $sourcePath, array $masks): string
    {
        $image = imagecreatefromjpeg($sourcePath);
        if (false === $image) {
            throw new \RuntimeException('Stored photo could not be read.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $black = (int) imagecolorallocate($image, 0, 0, 0);
        foreach ($masks as $mask) {
            imagefilledrectangle(
                $image,
                max(0, (int) floor(($mask->x - self::MARGIN) * $width)),
                max(0, (int) floor(($mask->y - self::MARGIN) * $height)),
                min($width - 1, (int) ceil(($mask->x + $mask->width + self::MARGIN) * $width)),
                min($height - 1, (int) ceil(($mask->y + $mask->height + self::MARGIN) * $height)),
                $black,
            );
        }

        $target = tempnam(sys_get_temp_dir(), 'redacted_');
        if (false === $target || !imagejpeg($image, $target, 85)) {
            throw new \RuntimeException('Masked photo could not be written.');
        }

        return $target;
    }
}
