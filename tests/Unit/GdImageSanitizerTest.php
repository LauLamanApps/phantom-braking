<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\Exception\InvalidImageException;
use App\Incident\Infrastructure\Service\GdImageSanitizer;
use App\Tests\Support\JpegFixture;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class GdImageSanitizerTest extends TestCase
{
    public function testFixtureReallyCarriesTheMetadata(): void
    {
        $path = JpegFixture::create(40, 20, 6);

        $exif = exif_read_data($path);
        self::assertIsArray($exif);
        self::assertSame(6, $exif['Orientation']);
        self::assertStringContainsString(JpegFixture::SECRET, (string) file_get_contents($path));
        unlink($path);
    }

    public function testStripsAllMetadataAndConsumesTheOriginal(): void
    {
        $path = JpegFixture::create(40, 20, 1, extraTail: '<?php echo "polyglot"; ?>');

        $result = (new GdImageSanitizer())->sanitize($path);
        $bytes = (string) file_get_contents($result->path);

        self::assertFileDoesNotExist($path, 'the original with metadata must be deleted');
        self::assertStringNotContainsString(JpegFixture::SECRET, $bytes);
        self::assertStringNotContainsString('Exif', $bytes);
        self::assertStringNotContainsString('<?php', $bytes);
        self::assertSame('image/jpeg', $result->mimeType);
        self::assertSame(\strlen($bytes), $result->size);
        unlink($result->path);
    }

    #[TestWith([1, 40, 20])]
    #[TestWith([3, 40, 20])]
    #[TestWith([6, 20, 40])]
    #[TestWith([8, 20, 40])]
    #[TestWith([5, 20, 40])]
    #[TestWith([7, 20, 40])]
    public function testBakesExifOrientationIntoThePixels(int $orientation, int $width, int $height): void
    {
        $result = (new GdImageSanitizer())->sanitize(JpegFixture::create(40, 20, $orientation));

        self::assertSame([$width, $height], [$result->width, $result->height]);
        self::assertSame([$width, $height], \array_slice((array) getimagesize($result->path), 0, 2));
        unlink($result->path);
    }

    public function testRotatesClockwiseForOrientation6(): void
    {
        // Left half is red. Orientation 6 means "rotate 90° clockwise to view": red ends up on top.
        $result = (new GdImageSanitizer())->sanitize(JpegFixture::create(40, 20, 6));
        $image = imagecreatefromjpeg($result->path);
        self::assertNotFalse($image);

        self::assertGreaterThan(150, self::red($image, 10, 5), 'top should be red');
        self::assertLessThan(100, self::red($image, 10, 35), 'bottom should not be red');
        unlink($result->path);
    }

    public function testScalesDownLargePhotos(): void
    {
        $result = (new GdImageSanitizer())->sanitize(JpegFixture::create(3000, 1500));

        self::assertSame([2560, 1280], [$result->width, $result->height]);
        unlink($result->path);
    }

    public function testFlattensTransparentPngOntoWhite(): void
    {
        $png = imagecreatetruecolor(10, 10);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, (int) imagecolorallocatealpha($png, 0, 0, 0, 127));
        $path = (string) tempnam(sys_get_temp_dir(), 'png_');
        imagepng($png, $path);

        $result = (new GdImageSanitizer())->sanitize($path);
        $image = imagecreatefromjpeg($result->path);
        self::assertNotFalse($image);
        self::assertGreaterThan(240, self::red($image, 5, 5));
        unlink($result->path);
    }

    public function testRejectsFilesThatAreNotImages(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fake_');
        file_put_contents($path, "<?php echo 'not a photo';");

        try {
            $this->expectException(InvalidImageException::class);
            (new GdImageSanitizer())->sanitize($path);
        } finally {
            unlink($path);
        }
    }

    private static function red(\GdImage $image, int $x, int $y): int
    {
        $color = imagecolorat($image, $x, $y);
        self::assertIsInt($color);

        return imagecolorsforindex($image, $color)['red'];
    }
}
