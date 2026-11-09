<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Infrastructure\Service\GdImageRedactor;
use App\Tests\Support\JpegFixture;
use PHPUnit\Framework\TestCase;

final class GdImageRedactorTest extends TestCase
{
    public function testBlacksOutMasksOnACopyAndLeavesTheSourceAlone(): void
    {
        $source = JpegFixture::create(400, 200); // left half red, right half black-ish background
        $before = (string) file_get_contents($source);

        $result = (new GdImageRedactor())->redact($source, [PlateMask::fromArray([0.1, 0.25, 0.2, 0.2])]);

        self::assertSame($before, file_get_contents($source), 'the private original is untouched');
        $image = imagecreatefromjpeg($result);
        self::assertNotFalse($image);
        self::assertLessThan(30, self::red($image, 80, 70), 'inside the mask: black');
        self::assertGreaterThan(150, self::red($image, 20, 20), 'outside the mask: still red');
        unlink($source);
        unlink($result);
    }

    private static function red(\GdImage $image, int $x, int $y): int
    {
        $colour = imagecolorat($image, $x, $y);
        self::assertIsInt($colour);

        return imagecolorsforindex($image, $colour)['red'];
    }
}
