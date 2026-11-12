<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\ValueObject\IncidentId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class IdTest extends TestCase
{
    public function testGeneratesUuidV7WithTheCurrentTime(): void
    {
        $before = new \DateTimeImmutable('-1 second');
        $id = (string) IncidentId::generate();

        self::assertTrue(UuidV7::isValid($id), $id);
        $created = UuidV7::fromString($id)->getDateTime();
        self::assertGreaterThanOrEqual($before, $created);
        self::assertLessThanOrEqual(new \DateTimeImmutable('+1 second'), $created);
        self::assertNotSame($id, (string) IncidentId::generate());
    }

    public function testNewIdsSortByCreationTime(): void
    {
        $first = (string) IncidentId::generate();
        usleep(2000);

        self::assertLessThan(0, strcmp($first, (string) IncidentId::generate()));
    }

    public function testAcceptsExistingV4IdsAndRejectsOthers(): void
    {
        self::assertSame('0b6e2f3c-1a4d-4c8e-9f2a-3b5c7d9e1f20', (string) IncidentId::fromString('0B6E2F3C-1A4D-4C8E-9F2A-3B5C7D9E1F20'));
        self::assertSame('019a7b3e-5f10-7c8e-9f2a-3b5c7d9e1f20', (string) IncidentId::fromString('019a7b3e-5f10-7c8e-9f2a-3b5c7d9e1f20'));

        $this->expectException(\InvalidArgumentException::class);
        IncidentId::fromString('019a7b3e-5f10-1c8e-9f2a-3b5c7d9e1f20'); // v1
    }
}
