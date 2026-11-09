<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Vehicle\Domain\Exception\InvalidLicensePlateException;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class LicensePlateTest extends TestCase
{
    #[TestWith(['gv-n31-k', 'GVN31K'])]
    #[TestWith([' AB 123 C ', 'AB123C'])]
    #[TestWith(['1-abc-23', '1ABC23'])]
    public function testNormalises(string $input, string $expected): void
    {
        self::assertSame($expected, LicensePlate::fromInput($input)->value);
    }

    #[TestWith([''])]
    #[TestWith(['--'])]
    #[TestWith(['ABCDEFGHIJK'])]
    public function testRejectsInvalid(string $input): void
    {
        $this->expectException(InvalidLicensePlateException::class);
        LicensePlate::fromInput($input);
    }
}
