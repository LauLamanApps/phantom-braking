<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Presentation\Cli\SeedDemoDataCli;
use App\Vehicle\Domain\ValueObject\Country;
use PHPUnit\Framework\TestCase;

final class SeedDemoDataTest extends TestCase
{
    public function testEveryIncidentCountryHasPlacesToSeed(): void
    {
        $seeder = new \ReflectionClass(SeedDemoDataCli::class);
        $places = array_keys($seeder->getConstant('SPOTS') + $seeder->getConstant('CITIES'));

        sort($places);
        $europe = IncidentCountry::EUROPE;
        sort($europe);
        self::assertSame($europe, $places);
    }

    public function testEveryRegistrationCountryHasAFleet(): void
    {
        $fleets = (new \ReflectionClass(SeedDemoDataCli::class))->getConstant('VEHICLES');

        foreach (Country::cases() as $country) {
            self::assertNotEmpty($fleets[$country->value] ?? [], $country->value);
        }
    }
}
