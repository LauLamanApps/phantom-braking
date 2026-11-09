<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Exception\InvalidImageException;
use App\Incident\Domain\Service\ImageSanitizerInterface;
use App\Incident\Domain\Service\SanitizedImage;

/**
 * Re-encodes uploads with GD. Decoding and writing a fresh JPEG drops every
 * metadata block (EXIF incl. GPS, XMP, IPTC, comments) and anything appended
 * to the file, so what we store is just pixels.
 */
final class GdImageSanitizer implements ImageSanitizerInterface
{
    private const int MAX_EDGE = 2560;
    private const int MAX_PIXELS = 40_000_000; // refuse decompression bombs before decoding
    private const int JPEG_QUALITY = 85;

    public function sanitize(string $path): SanitizedImage
    {
        $info = self::quietly(static fn (): array|false => getimagesize($path));
        if (false === $info || !\in_array($info[2], [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP], true)) {
            throw new InvalidImageException('Not a JPEG, PNG or WebP image.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidImageException('Image has too many pixels.');
        }

        $image = self::quietly(static fn (): \GdImage|false => match ($info[2]) {
            \IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => imagecreatefrompng($path),
            default => imagecreatefromwebp($path),
        });
        if (false === $image) {
            throw new InvalidImageException('Image could not be decoded.');
        }

        if (\IMAGETYPE_JPEG === $info[2]) {
            $image = $this->applyExifOrientation($image, $path);
        }
        $image = $this->fitAndFlatten($image);

        $target = tempnam(sys_get_temp_dir(), 'photo_');
        if (false === $target || !imagejpeg($image, $target, self::JPEG_QUALITY)) {
            throw new InvalidImageException('Image could not be encoded.');
        }

        unlink($path); // the original, with its metadata, is never kept

        return new SanitizedImage($target, 'image/jpeg', 'jpg', imagesx($image), imagesy($image), (int) filesize($target));
    }

    /** Phones store the rotation in EXIF; bake it into the pixels before the metadata is dropped. */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) {
            return $image;
        }

        $exif = self::quietly(static fn (): array|false => exif_read_data($path));
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }
        // imagerotate() turns counter-clockwise. Mirrored cases (2, 4, 5, 7) were flipped above first.
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270, // 90° clockwise
            5, 8 => 90,  // 90° counter-clockwise
            default => 0,
        };

        return 0 === $angle ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    /** Scales down to MAX_EDGE and paints transparency onto white (JPEG has no alpha). */
    private function fitAndFlatten(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /**
     * GD and EXIF functions emit warnings on malformed input; we turn those into a
     * return value instead (and never echo the file name into logs).
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private static function quietly(callable $operation): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
