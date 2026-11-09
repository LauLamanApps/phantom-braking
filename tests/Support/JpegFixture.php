<?php

declare(strict_types=1);

namespace App\Tests\Support;

/** Builds real JPEG files, optionally with an EXIF block like a phone would write. */
final class JpegFixture
{
    public const string SECRET = 'SECRET-GPS-52.0907N-5.1214E';

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function create(int $width, int $height, ?int $orientation = null, string $extraTail = ''): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($image, 220, 40, 40));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        if (null !== $orientation) {
            // SOI, then our APP1/Exif segment, then the rest of the file.
            $jpeg = substr($jpeg, 0, 2).self::exifSegment($orientation, self::SECRET).substr($jpeg, 2);
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'fixture_');
        file_put_contents($path, $jpeg.$extraTail);

        return $path;
    }

    /** Minimal little-endian TIFF/EXIF: IFD0 with ImageDescription (our marker) and Orientation. */
    private static function exifSegment(int $orientation, string $description): string
    {
        $data = $description."\0";
        $dataOffset = 8 + 2 + 2 * 12 + 4;

        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvV', 0x010E, 2, \strlen($data)).pack('V', $dataOffset)  // ImageDescription
            .pack('vvV', 0x0112, 3, 1).pack('vv', $orientation, 0)          // Orientation
            .pack('V', 0)
            .$data;

        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', 2 + \strlen($payload)).$payload;
    }
}
