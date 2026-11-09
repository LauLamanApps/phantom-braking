<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Statistics\Presentation\Twig\RoadSignExtension;
use App\Vehicle\Domain\ValueObject\Country;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class RoadSignExtensionTest extends TestCase
{
    #[TestWith(['NL', 'A15', 'bg-red-700'])]
    #[TestWith(['NL', 'N325', 'bg-yellow-400'])]
    #[TestWith(['DE', 'A7', 'bg-blue-700'])]
    #[TestWith(['DE', 'B10', 'bg-yellow-400'])]
    #[TestWith(['FR', 'A7', 'bg-red-700'])]
    #[TestWith(['FR', 'N7', 'bg-red-700'])]
    #[TestWith(['FR', 'D906', 'bg-yellow-400'])]
    #[TestWith(['DE', 'E35', 'bg-green-700'])]
    #[TestWith(['NL', 'S100', 'bg-stone-200'])]
    public function testColoursRoadLikeItsCountrysSign(string $country, string $road, string $expected): void
    {
        self::assertStringContainsString($expected, (new RoadSignExtension())->roadSignClass($country, $road));
    }

    public function testAcceptsTheEnum(): void
    {
        self::assertStringContainsString('bg-blue-700', (new RoadSignExtension())->roadSignClass(Country::Germany, 'a9'));
    }
}
