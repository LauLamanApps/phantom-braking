<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Presentation\Filter\IncidentFilterQuery;
use App\Incident\Presentation\Filter\Period;
use PHPUnit\Framework\TestCase;

final class IncidentFilterQueryTest extends TestCase
{
    private const string NOW = '2026-11-09 14:00';

    public function testParsesAndRoundTripsTheUrl(): void
    {
        $query = IncidentFilterQuery::fromQuery([
            'country' => 'nl', 'make' => ' tesla ', 'model' => 'model y', 'road_type' => 'motorway', 'period' => '90d',
        ]);

        self::assertSame('NL', $query->country?->code);
        self::assertSame('TESLA', $query->make);
        self::assertSame('MODEL Y', $query->model);
        self::assertSame(RoadType::Motorway, $query->roadType);
        self::assertSame(Period::Last90Days, $query->period);
        self::assertSame(
            ['country' => 'NL', 'make' => 'TESLA', 'model' => 'MODEL Y', 'road_type' => 'motorway', 'period' => '90d'],
            $query->toQuery(),
        );
        self::assertTrue($query->isActive());

        $filter = $query->toFilter(new \DateTimeImmutable(self::NOW));
        self::assertSame('2026-08-12', $filter->from?->format('Y-m-d'));
        self::assertSame('2026-11-09', $filter->to?->format('Y-m-d'));
    }

    public function testAcceptsIncidentCountriesWithoutARegistry(): void
    {
        self::assertSame('BE', IncidentFilterQuery::fromQuery(['country' => 'be'])->country?->code);
    }

    public function testIgnoresGarbage(): void
    {
        $query = IncidentFilterQuery::fromQuery([
            'country' => 'US', 'road_type' => 'moon', 'period' => 'forever', 'from' => 'yesterday', 'make' => ['array'], 'model' => 'ORPHAN',
        ]);

        self::assertNull($query->country);
        self::assertNull($query->roadType);
        self::assertSame(Period::All, $query->period);
        self::assertNull($query->from);
        self::assertNull($query->make);
        self::assertNull($query->model, 'a model without a make is dropped');
        self::assertFalse($query->isActive());
        self::assertEquals(IncidentFilter::all(), $query->toFilter(new \DateTimeImmutable(self::NOW)));
    }

    public function testCustomPeriodUsesDatesAndSwapsThemWhenReversed(): void
    {
        $query = IncidentFilterQuery::fromQuery(['from' => '2026-06-30', 'to' => '2026-01-01']);

        self::assertSame(Period::Custom, $query->period);
        $filter = $query->toFilter(new \DateTimeImmutable(self::NOW));
        self::assertSame('2026-01-01', $filter->from?->format('Y-m-d'));
        self::assertSame('2026-06-30', $filter->to?->format('Y-m-d'));
    }

    public function testDatesAreIgnoredForPresetPeriods(): void
    {
        $query = IncidentFilterQuery::fromQuery(['period' => 'ytd', 'from' => '2020-01-01']);

        self::assertNull($query->from);
        self::assertSame('2026-01-01', $query->toFilter(new \DateTimeImmutable(self::NOW))->from?->format('Y-m-d'));
    }

    public function testModelIsDroppedWithoutMakeInTheDomainFilterToo(): void
    {
        $filter = new IncidentFilter(model: 'ID.3');

        self::assertNull($filter->model);
        self::assertSame('ID.3', (new IncidentFilter(make: 'volkswagen', model: 'id.3'))->model);
    }
}
