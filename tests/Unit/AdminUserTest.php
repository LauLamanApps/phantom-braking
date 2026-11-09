<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\ValueObject\AdminUserId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AdminUserTest extends TestCase
{
    public function testNameIsTidied(): void
    {
        self::assertSame('Anne-Marie van den Berg', (new AdminUser(AdminUserId::generate(), "  Anne-Marie   van den\tBerg "))->getName());
        self::assertSame("Renée O'Neill Jr.", (new AdminUser(AdminUserId::generate(), "Renée O'Neill Jr."))->getName());
    }

    #[TestWith([''])]
    #[TestWith(['A'])]
    #[TestWith(['<script>'])]
    #[TestWith(['admin@example.org'])]
    #[TestWith(['-dash first'])]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminUser(AdminUserId::generate(), $name);
    }

    public function testRejectsTooLongNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminUser(AdminUserId::generate(), str_repeat('a', AdminUser::MAX_NAME_LENGTH + 1));
    }
}
